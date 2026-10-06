<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

/**
 * Contract every payment provider implements. To add Razorpay or Stripe, create a class
 * implementing this interface (create the provider order in charge(), verify the webhook /
 * signature in a controller) and register it in PaymentManager::GATEWAYS.
 */
interface PaymentGateway
{
    /** Short identifier stored on payments.gateway */
    public function name(): string;

    /** Validation rules for the payment_details the client must send for this method. */
    public function rules(): array;

    public function charge(Order $order, Payment $payment, array $details): PaymentResult;

    public function refund(Payment $payment, float $amount): PaymentResult;
}
