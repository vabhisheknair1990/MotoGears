<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\VehicleManufacturer;
use Database\Seeders\ProductionSeeder;
use Tests\TestCase;

/** Live-store first start and the admin account commands used on the server. */
class ProductionSetupTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['ADMIN_EMAIL', 'ADMIN_PASSWORD', 'ADMIN_NAME'] as $k) {
            unset($_SERVER[$k], $_ENV[$k]);
        }
        parent::tearDown();
    }

    public function test_production_seed_has_essentials_and_admin_but_no_demo_data(): void
    {
        $_SERVER['ADMIN_EMAIL'] = $_ENV['ADMIN_EMAIL'] = 'owner@themotogears.in';
        $_SERVER['ADMIN_PASSWORD'] = $_ENV['ADMIN_PASSWORD'] = 'Str0ng-pass-2026';
        $this->seed(ProductionSeeder::class);

        $this->assertGreaterThan(0, Category::count());
        $this->assertGreaterThan(0, VehicleManufacturer::count());
        $this->assertSame(0, Product::count());
        $this->assertFalse(User::where('email', 'like', '%@example.com')->exists(), 'no demo logins on a live store');

        $admin = User::where('email', 'owner@themotogears.in')->firstOrFail();
        $this->assertTrue($admin->hasRole(Role::SUPER_ADMIN));
        $this->postJson(self::API.'/admin/auth/login', ['email' => 'owner@themotogears.in', 'password' => 'Str0ng-pass-2026'])->assertOk();
        $this->postJson(self::API.'/admin/auth/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertStatus(422);
    }

    public function test_without_admin_settings_no_user_is_created(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->assertSame(0, User::count());
    }

    public function test_create_admin_requires_a_strong_password(): void
    {
        $this->artisan('app:create-admin', ['email' => 'boss@themotogears.in', '--password' => 'password'])->assertExitCode(1);
        $this->artisan('app:create-admin', ['email' => 'boss@themotogears.in', '--password' => 'short1'])->assertExitCode(1);
        $this->artisan('app:create-admin', ['email' => 'not-an-email', '--password' => 'Str0ng-pass-2026'])->assertExitCode(1);
        $this->artisan('app:create-admin', ['email' => 'boss@themotogears.in', '--password' => 'Str0ng-pass-2026'])->assertExitCode(0);
        $this->assertTrue(User::where('email', 'boss@themotogears.in')->firstOrFail()->hasRole(Role::SUPER_ADMIN));
    }

    public function test_set_password_changes_it_and_signs_the_user_out(): void
    {
        $admin = $this->staff(Role::SUPER_ADMIN, ['email' => 'admin@example.com']);
        $admin->createToken('old');
        $this->artisan('app:set-password', ['email' => 'admin@example.com', '--password' => 'weak'])->assertExitCode(1);
        $this->artisan('app:set-password', ['email' => 'admin@example.com', '--password' => 'N3w-secure-pass'])->assertExitCode(0);
        $this->assertSame(0, $admin->tokens()->count());
        $this->postJson(self::API.'/admin/auth/login', ['email' => 'admin@example.com', 'password' => 'N3w-secure-pass'])->assertOk();
        $this->artisan('app:set-password', ['email' => 'nobody@example.com', '--password' => 'N3w-secure-pass'])->assertExitCode(1);
    }

    public function test_remove_demo_staff_locks_demo_logins(): void
    {
        $this->staff(Role::SUPER_ADMIN, ['email' => 'admin@example.com']);
        $this->staff(Role::CATALOG_MANAGER, ['email' => 'catalog@example.com']);
        $this->artisan('app:remove-demo-staff')->assertExitCode(0);
        $this->postJson(self::API.'/admin/auth/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertStatus(422);
        $this->assertFalse(User::where('email', 'catalog@example.com')->first()->isStaff());
    }
}
