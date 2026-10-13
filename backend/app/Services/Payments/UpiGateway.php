<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\Upi\UpiException;
use App\Services\Payments\Upi\UpiProviders;

/**
 * UPI paid on our own page (QR code). charge() only opens the provider order and
 * leaves the payment pending; UpiPaymentService then shows the QR / sends the collect
 * request and confirms the payment by polling the provider (plus the webhook).
 */
class UpiGateway implements PaymentGateway
{
    public function __construct(private UpiProviders $providers) {}

    public function name(): string
    {
        return $this->providers->current()?->name() ?? 'upi';
    }

    public function rules(): array
    {
        return []; // the UPI ID (if any) is sent later, from the payment page
    }

    public function charge(Order $order, Payment $payment, array $details): PaymentResult
    {
        $provider = $this->providers->current();
        if (! $provider) {
            return PaymentResult::failed('UPI payments are unavailable right now. Please choose another payment method.');
        }
        try {
            $reference = $provider->openOrder($order, $payment);
        } catch (UpiException $e) {
            return PaymentResult::failed($e->getMessage());
        }

        return PaymentResult::pending(null, ['gateway_order_id' => $reference]);
    }

    public function refund(Payment $payment, float $amount): PaymentResult
    {
        return $this->providers->forGateway($payment->gateway)->refund($payment, $amount);
    }
}
