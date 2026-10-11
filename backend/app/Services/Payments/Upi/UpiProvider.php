<?php

namespace App\Services\Payments\Upi;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentResult;

/**
 * Who actually moves the money for an in-page UPI payment (Razorpay S2S UPI, or the demo
 * simulator). Every method may throw UpiException when the provider refuses or is unreachable.
 */
interface UpiProvider
{
    /** Stored on payments.gateway, so later checks/refunds reach the same provider. */
    public function name(): string;

    public function isAvailable(): bool;

    public function isDemo(): bool;

    /** Create the provider-side order for one payment attempt; returns its reference. */
    public function openOrder(Order $order, Payment $payment): string;

    /**
     * Start an intent payment: the returned upi://pay link is shown as a QR code (and opens
     * the UPI app directly on phones).
     *
     * @param  array{ip?: ?string, user_agent?: ?string, referer?: ?string}  $client
     * @return array{link: string, reference: ?string}
     */
    public function intent(Order $order, Payment $payment, array $client): array;

    /**
     * Send a collect request to the customer's UPI ID; they approve it in their UPI app.
     *
     * @return array{reference: ?string}
     */
    public function collect(Order $order, Payment $payment, string $vpa, array $client): array;

    /**
     * Check a UPI ID before sending a request. Null when the provider can't verify it.
     *
     * @return array{valid: bool, name: ?string}|null
     */
    public function validateVpa(string $vpa): ?array;

    /** Ask the provider whether the attempt has been paid. */
    public function check(Payment $payment): UpiCheck;

    public function refund(Payment $payment, float $amount): PaymentResult;
}
