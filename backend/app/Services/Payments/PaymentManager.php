<?php

namespace App\Services\Payments;

use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

class PaymentManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    public const GATEWAYS = [
        'cod' => CashOnDeliveryGateway::class,
        'demo_card' => DemoCardGateway::class,
        'demo_upi' => DemoUpiGateway::class,
        'razorpay' => RazorpayGateway::class,
        'upi' => UpiGateway::class,
    ];

    /** Order shown at checkout. */
    private const DISPLAY_ORDER = [PaymentMethod::Upi, PaymentMethod::Razorpay, PaymentMethod::CashOnDelivery, PaymentMethod::DemoCard, PaymentMethod::DemoUpi];

    public function gateway(PaymentMethod|string $method): PaymentGateway
    {
        $key = $method instanceof PaymentMethod ? $method->value : $method;

        return app(self::GATEWAYS[$key]);
    }

    public function rulesFor(?string $method): array
    {
        return $method && isset(self::GATEWAYS[$method]) ? $this->gateway($method)->rules() : [];
    }

    /** Create a payment attempt (initiated), run it through the gateway and record the outcome. */
    public function process(Order $order, PaymentMethod $method, array $details): Payment
    {
        $gateway = $this->gateway($method);
        $payment = $order->payments()->create([
            'method' => $method->value,
            'gateway' => $gateway->name(),
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'status' => PaymentStatus::Initiated,
        ]);

        $result = $gateway->charge($order, $payment, $details);

        $payment->update([
            'status' => $result->status,
            'gateway_order_id' => $result->meta['gateway_order_id'] ?? null,
            'transaction_id' => $result->transactionId,
            'failure_reason' => $result->failureReason,
            'meta' => $result->meta,
            'paid_at' => $result->status === PaymentStatus::Success ? now() : null,
        ]);

        return $payment;
    }

    /** Refund the successful payment of an order (cancellation / refund status). */
    public function refund(Order $order): ?Payment
    {
        $payment = $order->payments()->where('status', PaymentStatus::Success)->first();

        return $payment ? $this->refundPayment($payment, $order) : null;
    }

    public function refundPayment(Payment $payment, Order $order): Payment
    {
        $result = $this->gateway($payment->method)->refund($payment, (float) $payment->amount);
        if ($result->status === PaymentStatus::Success) {
            $payment->update(['status' => PaymentStatus::Refunded, 'meta' => array_merge($payment->meta ?? [], ['refund_id' => $result->transactionId])]);
            if (! $order->payments()->where('status', PaymentStatus::Success)->exists()) {
                $order->update(['payment_status' => OrderPaymentStatus::Refunded]);
            }
        } else {
            $payment->update(['meta' => array_merge($payment->meta ?? [], ['refund_error' => $result->failureReason])]);
        }

        return $payment->fresh();
    }

    /** Methods shown at checkout: Razorpay only when keys are configured, demo methods only when enabled. */
    public static function methodOptions(): array
    {
        return array_values(array_map(fn (PaymentMethod $m) => [
            'code' => $m->value,
            'label' => $m->label(),
            'description' => $m === PaymentMethod::Razorpay ? self::razorpayDescription() : $m->description(),
        ], array_filter(self::DISPLAY_ORDER, fn (PaymentMethod $m) => self::isOffered($m))));
    }

    /** "UPI, cards, net banking and wallets" — only what Razorpay has switched on for the account. */
    private static function razorpayDescription(): string
    {
        $methods = app(Razorpay\RazorpayClient::class)->enabledMethods();
        if ($methods === null) {
            return PaymentMethod::Razorpay->description();
        }
        $names = array_values(array_filter([
            ! empty($methods['upi']) ? 'UPI' : null,
            ! empty($methods['card']) ? 'credit/debit cards' : null,
            ! empty(array_filter((array) ($methods['netbanking'] ?? []))) ? 'net banking' : null,
            ! empty(array_filter((array) ($methods['wallet'] ?? []))) ? 'wallets' : null,
        ]));
        if (! $names) {
            return PaymentMethod::Razorpay->description();
        }
        $last = array_pop($names);

        return ucfirst(($names ? implode(', ', $names).' and ' : '').$last).' — secure checkout by Razorpay.';
    }

    public static function isOffered(PaymentMethod $method): bool
    {
        return match (true) {
            $method === PaymentMethod::Razorpay => app(Razorpay\RazorpayClient::class)->isConfigured(),
            $method === PaymentMethod::Upi => app(Upi\UpiProviders::class)->current() !== null,
            // Replaced by the in-page UPI method; kept so old orders still display and refund.
            $method === PaymentMethod::DemoUpi => false,
            $method->isDemo() => (bool) config('payments.demo_methods', true),
            default => true,
        };
    }
}
