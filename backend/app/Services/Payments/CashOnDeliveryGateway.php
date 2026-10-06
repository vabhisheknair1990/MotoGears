<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

class CashOnDeliveryGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'cod';
    }

    public function rules(): array
    {
        return [];
    }

    public function charge(Order $order, Payment $payment, array $details): PaymentResult
    {
        // Nothing is collected now; the payment is marked successful when the order is delivered.
        return PaymentResult::pending('COD-'.$order->order_number, ['collect_on_delivery' => true]);
    }

    public function refund(Payment $payment, float $amount): PaymentResult
    {
        return PaymentResult::success('COD-REFUND-'.$payment->id);
    }
}
