<?php

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    public const DEFAULTS = [
        'store_name' => 'MotoGears',
        'store_tagline' => 'Genuine parts. Perfect fit.',
        'support_phone' => '+91 80 4719 2200',
        'support_email' => 'support@motogears.example',
        'store_address' => '14 Industrial Layout, Peenya, Bengaluru 560058',
        'gstin' => '29ABCDE1234F1Z5',
        'currency' => 'INR',
        'free_shipping_threshold' => 2999,
        'standard_shipping_cost' => 100,
        'express_shipping_cost' => 250,
        'shipping_tax_rate' => 18,
        'cod_enabled' => true,
        'cod_max_order_value' => 50000,
        'unpaid_order_timeout_minutes' => 30,
        'max_quantity_per_item' => 10,
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
        ])), $public);
    }
}
