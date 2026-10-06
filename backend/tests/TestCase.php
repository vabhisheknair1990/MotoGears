<?php

namespace Tests;

use App\Models\Address;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected const API = '/v1';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
        $this->withHeaders(['Accept' => 'application/json']);
    }

    protected function customer(array $attrs = []): User
    {
        return User::factory()->create($attrs)->assignRole(Role::CUSTOMER);
    }

    protected function staff(string $role = Role::SUPER_ADMIN, array $attrs = []): User
    {
        return User::factory()->create($attrs)->assignRole($role);
    }

    protected function actingAsCustomer(?User $user = null): User
    {
        $user ??= $this->customer();
        Sanctum::actingAs($user, ['storefront']);

        return $user;
    }

    protected function actingAsStaff(string $role = Role::SUPER_ADMIN): User
    {
        $user = $this->staff($role);
        Sanctum::actingAs($user, ['admin', 'storefront']);

        return $user;
    }

    protected function product(array $attrs = [], int $stock = 50): Product
    {
        return Product::factory()->withStock($stock)->create($attrs);
    }

    protected function address(User $user): Address
    {
        return Address::factory()->for($user)->create();
    }

    protected function api(string $path): string
    {
        return self::API.'/'.ltrim($path, '/');
    }
}
