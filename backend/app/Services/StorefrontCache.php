<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class StorefrontCache
{
    public const KEYS = ['storefront.homepage', 'storefront.category_tree', 'storefront.brands', 'storefront.menu'];

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }
    }

    public static function remember(string $key, \Closure $callback, int $seconds = 600): mixed
    {
        return Cache::remember($key, $seconds, $callback);
    }
}
