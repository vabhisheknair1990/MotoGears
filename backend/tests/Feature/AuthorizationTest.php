<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_customers_cannot_reach_admin_apis(): void
    {
        $this->actingAsCustomer();

        $this->getJson($this->api('admin/dashboard'))->assertForbidden()->assertJsonPath('success', false);
        $this->postJson($this->api('admin/products'), [])->assertForbidden();
    }

    public function test_guests_get_401_on_admin_apis(): void
    {
        $this->getJson($this->api('admin/orders'))->assertUnauthorized();
    }

    public function test_permissions_are_enforced_per_role(): void
    {
        $this->actingAsStaff(Role::CATALOG_MANAGER);
        $this->getJson($this->api('admin/products'))->assertOk();
        $this->getJson($this->api('admin/categories'))->assertOk();
        $this->getJson($this->api('admin/orders'))->assertForbidden();
        $this->getJson($this->api('admin/settings'))->assertForbidden();

        $this->actingAsStaff(Role::ORDER_MANAGER);
        $this->getJson($this->api('admin/orders'))->assertOk();
        $this->getJson($this->api('admin/products'))->assertForbidden();

        $this->actingAsStaff(Role::CONTENT_MANAGER);
        $this->getJson($this->api('admin/banners'))->assertOk();
        $this->getJson($this->api('admin/inventory'))->assertForbidden();
    }

    public function test_super_admin_can_access_everything(): void
    {
        $this->actingAsStaff(Role::SUPER_ADMIN);
        foreach (['dashboard', 'products', 'orders', 'customers', 'inventory', 'coupons', 'reviews', 'banners', 'settings', 'staff', 'roles', 'audit-logs', 'reports/sales'] as $path) {
            $this->getJson($this->api("admin/{$path}"))->assertOk();
        }
    }

    public function test_customers_cannot_view_other_customers_orders(): void
    {
        $owner = $this->customer();
        $order = Order::create([
            'order_number' => 'MG-TEST-1', 'user_id' => $owner->id, 'status' => 'confirmed', 'payment_status' => 'paid', 'payment_method' => 'cod',
            'subtotal' => 100, 'grand_total' => 118, 'billing_address' => [], 'shipping_address' => [],
        ]);

        $this->actingAsCustomer();
        $this->getJson($this->api("orders/{$order->id}"))->assertForbidden();
        $this->postJson($this->api("orders/{$order->id}/cancel"))->assertForbidden();

        $this->actingAsCustomer($owner);
        $this->getJson($this->api("orders/{$order->id}"))->assertOk();
    }
}
