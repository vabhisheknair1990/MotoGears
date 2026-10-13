<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\Razorpay\RazorpayPaymentService;
use App\Services\Payments\Upi\UpiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The response for "place order" and "pay again" (customer and guest), so every caller
 * gets the same shape: what happened, plus what the browser must do next
 * (open the Razorpay window, or show the UPI QR / UPI ID page).
 */
class PaymentPayload
{
    public function __construct(private RazorpayPaymentService $razorpay, private UpiPaymentService $upi) {}

    /** paid | razorpay | upi | offline (cash on delivery) | failed */
    public function state(Payment $payment): string
    {
        $pending = $payment->status === PaymentStatus::Pending;

        return match (true) {
            $payment->status === PaymentStatus::Success => 'paid',
            $pending && $payment->method === PaymentMethod::Razorpay->value => 'razorpay',
            $pending && $payment->method === PaymentMethod::Upi->value => 'upi',
            $pending => 'offline',
            default => 'failed',
        };
    }

    public function placed(Request $request, Order $order, Payment $payment, array $extra = []): JsonResponse
    {
        $state = $this->state($payment);

        return response()->json([
            'success' => true,
            'message' => match ($state) {
                'paid' => 'Order placed successfully',
                'razorpay' => 'Order created — complete the payment in the Razorpay window',
                'upi' => 'Order created — complete the payment with UPI',
                'offline' => 'Order placed successfully — pay on delivery',
                default => 'Order created but payment failed: '.$payment->failure_reason,
            },
            'data' => $this->data($request, $order, $payment, $state, in_array($state, ['paid', 'offline'], true)) + $extra,
        ], 201);
    }

    public function retried(Request $request, Order $order, Payment $payment): JsonResponse
    {
        $state = $this->state($payment);
        $ok = in_array($state, ['paid', 'razorpay', 'upi'], true);

        return response()->json([
            'success' => $ok,
            'message' => match ($state) {
                'paid' => 'Payment successful',
                'razorpay' => 'Complete the payment in the Razorpay window',
                'upi' => 'Scan the QR code with any UPI app to pay',
                default => 'Payment failed: '.$payment->failure_reason,
            },
            'data' => $this->data($request, $order->fresh(), $payment, $state, $state === 'paid'),
        ], $ok ? 200 : 402);
    }

    private function data(Request $request, Order $order, Payment $payment, string $state, bool $successful): array
    {
        return [
            'order' => (new OrderResource($order->load(['items', 'statusHistory', 'payment'])))->resolve($request),
            'payment' => (new PaymentResource($payment))->resolve($request),
            'payment_successful' => $successful,
            'razorpay' => $state === 'razorpay' ? $this->razorpay->checkoutOptions($order, $payment) : null,
            'upi' => $state === 'upi' ? $this->upi->info($order) : null,
        ];
    }
}
