<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Services\Payments\Razorpay\RazorpayClient;
use App\Services\Payments\Razorpay\RazorpayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayController extends Controller
{
    public function __construct(private RazorpayPaymentService $razorpay, private RazorpayClient $client) {}

    /** POST /orders/{order}/razorpay/verify — called by the browser after checkout.js success. */
    public function verify(Request $request, string $order): JsonResponse
    {
        $model = $this->resolve($order);
        $this->authorize('pay', $model);
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:64'],
            'razorpay_payment_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'max:128'],
        ]);
        $payment = $this->razorpay->confirmFromCheckout($model, $data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'], $request->user());
        $ok = $payment->status->value === 'success';

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Payment successful' : 'Payment failed: '.$payment->failure_reason,
            'data' => [
                'order' => (new OrderResource($model->fresh()->load(['items', 'statusHistory', 'payment'])))->resolve($request),
                'payment' => (new PaymentResource($payment))->resolve($request),
                'payment_successful' => $ok,
            ],
        ], $ok ? 200 : 402);
    }

    /** POST /orders/{order}/razorpay/failed — checkout.js `payment.failed` event. */
    public function failed(Request $request, string $order): JsonResponse
    {
        $model = $this->resolve($order);
        $this->authorize('pay', $model);
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:250'],
        ]);
        $payment = $this->razorpay->reportFailure($model, $data['razorpay_order_id'], $data['reason'] ?? null, $request->user());

        return $this->ok(['payment' => (new PaymentResource($payment))->resolve($request)], 'Payment failure recorded');
    }

    /** POST /webhooks/razorpay — server-to-server, authenticated by HMAC signature only. */
    public function webhook(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        if (! $this->client->verifyWebhookSignature($raw, $request->header('X-Razorpay-Signature'))) {
            Log::warning('Rejected Razorpay webhook with invalid signature', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }
        $result = $this->razorpay->handleWebhook(json_decode($raw, true) ?: []);

        return response()->json(['success' => true, 'message' => 'Webhook processed', 'data' => ['result' => $result]]);
    }

    private function resolve(string $order): Order
    {
        return Order::where(ctype_digit($order) ? 'id' : 'order_number', $order)->firstOrFail();
    }
}
