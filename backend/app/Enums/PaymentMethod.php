<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CashOnDelivery = 'cod';
    case Razorpay = 'razorpay';
    case DemoCard = 'demo_card';
    case DemoUpi = 'demo_upi';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on Delivery',
            self::Razorpay => 'Razorpay',
            self::DemoCard => 'Card (Demo)',
            self::DemoUpi => 'UPI (Demo)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Pay in cash when your order is delivered.',
            self::Razorpay => 'UPI, credit/debit cards, net banking and wallets — secure checkout by Razorpay.',
            self::DemoCard => 'Simulated card payment. Use 4111 1111 1111 1111 for success, 4000 0000 0000 0002 to simulate a decline.',
            self::DemoUpi => 'Simulated UPI payment. Use success@upi for success, failure@upi to simulate a failure.',
        };
    }

    /** Paid in a browser popup and confirmed asynchronously (signature check + webhook). */
    public function isRedirectFlow(): bool
    {
        return $this === self::Razorpay;
    }

    public function isDemo(): bool
    {
        return in_array($this, [self::DemoCard, self::DemoUpi], true);
    }

    /** Methods a customer can use to (re)try paying an unpaid online order. */
    public static function onlineValues(): array
    {
        return [self::Razorpay->value, self::DemoCard->value, self::DemoUpi->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
