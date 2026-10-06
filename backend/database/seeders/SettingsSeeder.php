<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'store_name' => 'store', 'store_tagline' => 'store', 'support_phone' => 'store', 'support_email' => 'store', 'store_address' => 'store', 'gstin' => 'store', 'currency' => 'store',
            'free_shipping_threshold' => 'shipping', 'standard_shipping_cost' => 'shipping', 'express_shipping_cost' => 'shipping', 'shipping_tax_rate' => 'shipping',
            'cod_enabled' => 'payments', 'cod_max_order_value' => 'payments', 'unpaid_order_timeout_minutes' => 'payments', 'max_quantity_per_item' => 'checkout',
        ];
        $public = ['store_name', 'store_tagline', 'support_phone', 'support_email', 'store_address', 'currency', 'free_shipping_threshold', 'standard_shipping_cost', 'express_shipping_cost', 'cod_enabled'];

        foreach (SettingsService::DEFAULTS as $key => $value) {
            Setting::updateOrCreate(['key' => $key], [
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                'type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'string'),
                'group' => $groups[$key] ?? 'general',
                'is_public' => in_array($key, $public, true),
            ]);
        }
    }
}
