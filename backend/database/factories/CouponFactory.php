<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'TEST'.strtoupper(Str::random(5)),
            'name' => 'Test coupon',
            'type' => 'percentage',
            'value' => 10,
            'min_order_amount' => 0,
            'max_discount' => null,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ];
    }
}
