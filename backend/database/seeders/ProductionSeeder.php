<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\StorefrontCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * First-start data for a live store: roles & permissions, store settings, the category/brand/
 * attribute starter catalogue and the vehicle database — but no demo products, orders,
 * customers or demo staff logins. The first admin comes from ADMIN_EMAIL / ADMIN_PASSWORD;
 * without them, create one with `php artisan app:create-admin`.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
            CatalogSeeder::class,
            VehicleSeeder::class,
        ]);

        $email = trim((string) env('ADMIN_EMAIL', ''));
        $password = (string) env('ADMIN_PASSWORD', '');
        if ($email !== '' && $password !== '' && ! User::where('email', $email)->exists()) {
            $admin = User::create(['name' => env('ADMIN_NAME', 'Store Owner'), 'email' => $email, 'password' => $password]);
            $admin->forceFill(['email_verified_at' => now(), 'is_active' => true])->save();
            $admin->roles()->sync(Role::where('name', Role::SUPER_ADMIN)->pluck('id'));
            $this->command?->info("Super admin {$email} created.");
        } elseif ($email === '' || $password === '') {
            $this->command?->warn('No ADMIN_EMAIL/ADMIN_PASSWORD set — create the first admin with: php artisan app:create-admin');
        }

        StorefrontCache::flush();
        Cache::forget('settings.all');
    }
}
