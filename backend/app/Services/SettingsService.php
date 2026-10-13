<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    public const DEFAULTS = [
        'store_name' => 'MotoGears',
        'store_tagline' => 'Genuine parts. Perfect fit.',
        'support_phone' => '+91 77366 88668',
        'support_email' => 'motogears46@gmail.com',
        // Business details as on the GST registration certificate (Form GST REG-06).
        'trade_name' => 'M/S MOTO GEARS',
        'legal_name' => 'Ashwin Joe Thomas',
        'store_address' => 'Oolanpara Road, Ground Floor, TC 31/3483/3, Ambalamukku, Perurnagar, Thiruvananthapuram, Kerala 695005',
        'gstin' => '32AWCPT0591R2ZY',
        'currency' => 'INR',
        'free_shipping_threshold' => 2999,
        'standard_shipping_cost' => 100,
        'express_shipping_cost' => 250,
        'shipping_tax_rate' => 18,
        'cod_enabled' => true,
        'cod_max_order_value' => 50000,
        'unpaid_order_timeout_minutes' => 30,
        'max_quantity_per_item' => 10,
        'guest_checkout_enabled' => true,
        // Off for now: the store sells bike parts, riding gear, accessories and oils only.
        'sell_car_parts' => false,
    ];

    public function get(string $key): mixed
    {
        return Setting::get($key, self::DEFAULTS[$key] ?? null);
    }

    public function float(string $key): float
    {
        return (float) $this->get($key);
    }

    public function publicSettings(): array
    {
        $public = Setting::where('is_public', true)->get()->mapWithKeys(fn ($s) => [$s->key => $s->typedValue()])->all();

        return array_merge(array_intersect_key(self::DEFAULTS, array_flip([
            'store_name', 'store_tagline', 'support_phone', 'support_email', 'store_address', 'currency', 'free_shipping_threshold',
            'trade_name', 'legal_name', 'gstin', 'sell_car_parts',
            'guest_checkout_enabled',
        ])), $public);
    }
}
