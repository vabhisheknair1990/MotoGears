<?php

namespace App\Models\Concerns;

use App\Services\StorefrontCache;

/**
 * Clears cached storefront aggregates (homepage, category tree, brand list)
 * whenever a model that feeds them changes, so admin edits show up immediately.
 */
trait FlushesStorefrontCache
{
    public static function bootFlushesStorefrontCache(): void
    {
        foreach (['saved', 'deleted', 'restored'] as $event) {
            if ($event === 'restored' && ! method_exists(static::class, 'restore')) {
                continue;
            }
            static::$event(fn () => StorefrontCache::flush());
        }
    }
}
