<?php

namespace App\Enums;

enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::Fixed => 'Fixed amount',
            self::FreeShipping => 'Free shipping',
        };
    }
}
