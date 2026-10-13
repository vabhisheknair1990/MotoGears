<?php

namespace App\Support;

use App\Services\SettingsService;

/**
 * What the storefront currently sells. While "Sell car parts" is off (Admin → Settings), every
 * storefront query leaves out car-only products, categories, makes and models — nothing is
 * deleted, and the admin still sees and edits all of it.
 */
final class Catalog
{
    public static function sellsCars(): bool
    {
        return (bool) app(SettingsService::class)->get('sell_car_parts');
    }

    /** Lower-cased names of car-only makes and models (to filter free text such as testimonials). */
    public static function carNames(): array
    {
        static $names = null;

        return $names ??= \Illuminate\Support\Facades\Cache::remember('catalog.car_names', 600, fn () => array_values(array_unique(array_filter(array_map(
            fn ($n) => mb_strtolower(trim((string) $n)),
            array_merge(
                \App\Models\VehicleManufacturer::where('vehicle_type', 'car')->pluck('name')->all(),
                \App\Models\VehicleModel::where('vehicle_type', 'car')->pluck('name')->all(),
            ),
        )))));
    }

    /** True when free text mentions a car (the word "car"/"cars"/"SUV" or a car-only make or model). */
    public static function mentionsCar(?string $text): bool
    {
        $t = ' '.mb_strtolower((string) $text).' ';
        if (preg_match('/\b(car|cars|suv|suvs)\b/u', $t)) {
            return true;
        }
        foreach (self::carNames() as $name) {
            if ($name !== '' && preg_match('/\b'.preg_quote($name, '/').'\b/u', $t)) {
                return true;
            }
        }

        return false;
    }
}
