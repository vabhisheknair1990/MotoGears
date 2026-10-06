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

    /**
     * Cache a storefront payload as plain JSON data (arrays and scalars only).
     *
     * API resources nested inside a payload (e.g. a category's `children`) are objects; Laravel
     * stores cache values with serialize() and, with cache.serializable_classes = false, restores
     * objects as incomplete classes — they would come back as {"resource":…} objects instead of
     * lists. Converting to plain data first makes a cache hit identical to a fresh response.
     */
    public static function remember(string $key, \Closure $callback, int $seconds = 600): mixed
    {
        return Cache::remember($key, $seconds, fn () => self::plain($callback()));
    }

    public static function plain(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
    }
}
