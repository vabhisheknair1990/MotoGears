<?php

namespace App\Services\Payments\Upi;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentResult;

/**
 * Who actually moves the money for an in-page UPI payment (Razorpay UPI QR codes, or the
 * demo simulator). Every method may throw UpiException when the provider refuses or is unreachable.
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
     * Start a QR payment: the returned upi://pay link is shown as a QR code. `open_app` says
     * whether phones may open the link directly in a UPI app; `expires_at` (unix time) is when
     * the provider stops accepting it, when the provider decides that.
     *
     * @param  array{ip?: ?string, user_agent?: ?string, referer?: ?string}  $client
     * @param  list<string>  $previous  earlier references of this payment (to be closed)
     * @return array{link: string, reference: ?string, expires_at?: ?int, open_app?: bool}
     */
    public function intent(Order $order, Payment $payment, array $client, array $previous = []): array;

    /** Whether customers can type a UPI ID on our page (collect requests). */
    public function supportsCollect(): bool;

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
