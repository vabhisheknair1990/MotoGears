<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\Razorpay\RazorpayClient;
use App\Services\Payments\Razorpay\RazorpayException;

/**
 * Razorpay Standard Checkout.
 *  1. charge() creates a Razorpay Order for the exact server-computed total → payment stays "pending".
 *  2. The browser opens checkout.js with that order_id; the customer pays inside Razorpay.
 *  3. The success callback is verified server-side (HMAC signature + payment fetch) — see
 *     RazorpayPaymentService — and the webhook confirms it again if the browser never returns.
 */
class RazorpayGateway implements PaymentGateway
{
    public function __construct(private RazorpayClient $client) {}

    public function name(): string
    {
        return 'razorpay';
    }

    public function rules(): array
    {
        return []; // Nothing sensitive is ever sent to our server; the customer pays on Razorpay.
    }

    public function charge(Order $order, Payment $payment, array $details): PaymentResult
    {
        try {
            $rzpOrder = $this->client->createOrder(
                self::toPaise((float) $order->grand_total),
                $order->order_number.'-'.$payment->id,
                ['order_number' => $order->order_number, 'order_id' => $order->id, 'payment_id' => $payment->id],
                $order->currency,
            );
        } catch (RazorpayException $e) {
            return PaymentResult::failed('Could not start the Razorpay payment. Please try again in a moment.');
        }

        return PaymentResult::pending(null, [
            'gateway_order_id' => $rzpOrder['id'] ?? null,
            'razorpay_order_status' => $rzpOrder['status'] ?? null,
        ]);
    }

    public function refund(Payment $payment, float $amount): PaymentResult
    {
        if (! $payment->transaction_id) {
            return PaymentResult::failed('No Razorpay payment id recorded for this payment.');
        }
        try {
            $refund = $this->client->refund($payment->transaction_id, self::toPaise($amount), ['order' => (string) $payment->order?->order_number]);
        } catch (RazorpayException $e) {
            return PaymentResult::failed($e->getMessage());
        }

        return PaymentResult::success($refund['id'] ?? ('rfnd-'.$payment->transaction_id), ['refund_status' => $refund['status'] ?? null]);
    }

    public static function toPaise(float $rupees): int
    {
        return (int) round($rupees * 100);
    }
}
