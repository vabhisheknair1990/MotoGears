<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Payments\Upi\UpiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-page UPI for an order: QR code, UPI ID (collect) and status polling.
 * Serves both /orders/{id}/upi/* (customer, policy-checked) and
 * /guest/orders/{number}/upi/* (guest, order token).
 */
class UpiController extends Controller
{
    public function __construct(private UpiPaymentService $upi) {}

    /** POST …/upi/qr — show a QR code to scan with any UPI app (reused while still valid). */
    public function qr(Request $request, string $order): JsonResponse
    {
        $model = $this->order($request, $order, 'pay');

        return $this->ok($this->upi->qr($model, $request->user(), $this->client($request)), 'Scan the QR code with any UPI app');
    }

    /** POST …/upi/collect — send a payment request to the customer's UPI ID. */
    public function collect(Request $request, string $order): JsonResponse
    {
        $model = $this->order($request, $order, 'pay');
        $data = $request->validate([
            'vpa' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z][a-zA-Z0-9.\-]{1,63}$/'],
        ], ['vpa.regex' => 'Enter a valid UPI ID, like name@okhdfcbank.']);

        return $this->ok($this->upi->collect($model, $data['vpa'], $request->user(), $this->client($request)), 'Payment request sent — approve it in your UPI app');
    }

    /** GET …/upi/status — polled every few seconds by the payment page. */
    public function status(Request $request, string $order): JsonResponse
    {
        $model = $this->order($request, $order, 'view');
        $session = $this->upi->status($model, $request->user());

        return $this->ok($this->withOrder($request, $model, $session), $session['message'] ?? 'Waiting for payment');
    }

    /** POST …/upi/simulate — demo provider only: approve or decline the pending payment. */
    public function simulate(Request $request, string $order): JsonResponse
    {
        $model = $this->order($request, $order, 'pay');
        $data = $request->validate(['outcome' => ['required', 'in:success,failure']]);
        $session = $this->upi->simulate($model, $data['outcome'], $request->user());

        return $this->ok($this->withOrder($request, $model, $session), $session['message'] ?? 'Simulated');
    }

    /** Paid / failed sessions carry the updated order so the page can move on without another call. */
    private function withOrder(Request $request, Order $order, array $session): array
    {
        if (in_array($session['status'], ['paid', 'failed'], true)) {
            $session['order'] = (new OrderResource($order->fresh()->load(['items', 'statusHistory', 'payment'])))->resolve($request);
        }

        return $session;
    }

    private function order(Request $request, string $order, string $ability): Order
    {
        if ($request->routeIs('*guest.orders.*')) {
            return GuestOrderController::guestOrder($request, $order);
        }
        $model = Order::where(ctype_digit($order) ? 'id' : 'order_number', $order)->firstOrFail();
        $this->authorize($ability, $model);

        return $model;
    }

    /** Razorpay S2S needs the shopper's browser details. */
    private function client(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 250) ?: null,
            'referer' => $request->headers->get('referer') ?: config('app.frontend_url'),
        ];
    }
}
