<?php

namespace Database\Seeders;

use App\Services\StorefrontCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeding should not email customers or send low-stock alerts.
        Event::fake([\App\Events\LowStockDetected::class, \App\Events\OrderPlaced::class, \App\Events\OrderStatusChanged::class]);

        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
            CatalogSeeder::class,
            VehicleSeeder::class,
            UserSeeder::class,
            CouponSeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
            CmsSeeder::class,
        ]);

        StorefrontCache::flush();
        Cache::forget('settings.all');
    }
}
