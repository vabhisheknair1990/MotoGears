<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $stored = Setting::orderBy('group')->orderBy('key')->get()->keyBy('key');
        $all = collect(SettingsService::DEFAULTS)->map(function ($default, $key) use ($stored) {
            $s = $stored[$key] ?? null;

            return [
                'key' => $key,
                'value' => $s ? $s->typedValue() : $default,
                'type' => $s->type ?? (is_bool($default) ? 'boolean' : (is_numeric($default) ? 'number' : 'string')),
                'group' => $s->group ?? 'general',
                'is_public' => (bool) ($s->is_public ?? false),
            ];
        })->values();

        return $this->ok($all->groupBy('group'), 'Settings retrieved successfully');
    }

    public function update(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.store_name' => ['sometimes', 'string', 'max:100'],
            'settings.store_tagline' => ['sometimes', 'nullable', 'string', 'max:190'],
            'settings.support_phone' => ['sometimes', 'string', 'max:30'],
            'settings.support_email' => ['sometimes', 'email'],
            'settings.store_address' => ['sometimes', 'string', 'max:255'],
            'settings.gstin' => ['sometimes', 'string', 'max:20'],
            'settings.free_shipping_threshold' => ['sometimes', 'numeric', 'min:0'],
            'settings.standard_shipping_cost' => ['sometimes', 'numeric', 'min:0'],
            'settings.express_shipping_cost' => ['sometimes', 'numeric', 'min:0'],
            'settings.shipping_tax_rate' => ['sometimes', 'numeric', 'between:0,28'],
            'settings.cod_enabled' => ['sometimes', 'boolean'],
            'settings.cod_max_order_value' => ['sometimes', 'numeric', 'min:0'],
            'settings.unpaid_order_timeout_minutes' => ['sometimes', 'integer', 'min:5', 'max:10080'],
            'settings.max_quantity_per_item' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $old = Setting::allValues();
        foreach ($data['settings'] as $key => $value) {
            if (! array_key_exists($key, SettingsService::DEFAULTS)) {
                continue;
            }
            $default = SettingsService::DEFAULTS[$key];
            Setting::updateOrCreate(['key' => $key], [
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                'type' => is_bool($default) ? 'boolean' : (is_numeric($default) ? 'number' : 'string'),
            ]);
        }
        $audit->log('settings.updated', null, array_intersect_key($old, $data['settings']), $data['settings']);

        return $this->index();
    }
}
