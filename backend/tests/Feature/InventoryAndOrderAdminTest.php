<?php

namespace Tests\Feature;

use App\Events\LowStockDetected;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Role;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryAndOrderAdminTest extends TestCase
{
    private function placeOrder(int $qty = 2, int $stock = 10, string $method = 'cod'): array
    {
        Notification::fake();
        $customer = $this->actingAsCustomer();
        $p = $this->product(['price' => 1000, 'mrp' => 1000], stock: $stock);
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => $qty]);
        $order = $this->postJson($this->api('orders'), [
            'shipping_address_id' => $this->address($customer)->id, 'shipping_method' => 'standard', 'payment_method' => $method,
            'payment_details' => $method === 'demo_upi' ? ['upi_id' => 'ok@upi'] : null,
        ])->assertCreated()->json('data.order');

        return [$order, $p, $customer];
    }

    public function test_order_lifecycle_moves_stock_correctly(): void
    {
        [$order, $p] = $this->placeOrder(qty: 2, stock: 10);
        $this->actingAsStaff(Role::ORDER_MANAGER);

        foreach (['processing', 'packed'] as $s) {
            $this->patchJson($this->api("admin/orders/{$order['id']}/status"), ['status' => $s])->assertOk()->assertJsonPath('data.status', $s);
        }
        $this->patchJson($this->api("admin/orders/{$order['id']}/status"), ['status' => 'shipped', 'tracking_number' => 'BD123', 'carrier' => 'BlueDart'])
            ->assertOk()->assertJsonPath('data.tracking_number', 'BD123');

        $inv = Inventory::where('product_id', $p->id)->first();
        $this->assertSame(8, $inv->quantity);
        $this->assertSame(0, $inv->reserved);

        $this->patchJson($this->api("admin/orders/{$order['id']}/status"), ['status' => 'delivered'])
            ->assertOk()->assertJsonPath('data.payment_status', 'paid'); // COD collected on delivery

        $this->patchJson($this->api("admin/orders/{$order['id']}/status"), ['status' => 'returned'])->assertOk();
        $this->assertSame(10, Inventory::where('product_id', $p->id)->value('quantity'));

        $this->patchJson($this->api("admin/orders/{$order['id']}/status"), ['status' => 'refunded'])->assertOk()->assertJsonPath('data.payment_status', 'refunded');

        $timeline = collect($this->getJson($this->api("admin/orders/{$order['id']}"))->json('data.timeline'))->pluck('status');
        $this->assertEquals(['pending', 'confirmed', 'processing', 'packed', 'shipped', 'delivered', 'returned', 'refunded'], $timeline->unique()->values()->all());
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        [$order] = $this->placeOrder();
        $this->actingAsStaff(Role::ADMIN);

        $this->patchJson($this->api("admin/orders/{$order['id']}/status"), ['status' => 'delivered'])->assertStatus(422);
        $this->patchJson($this->api("admin/orders/{$order['id']}/status"), ['status' => 'bogus'])->assertStatus(422);
    }

    public function test_admin_cancel_releases_reservation(): void
    {
        [$order, $p] = $this->placeOrder(qty: 3, stock: 5, method: 'demo_upi');
        $this->actingAsStaff(Role::ADMIN);

        $this->patchJson($this->api("admin/orders/{$order['id']}/status"), ['status' => 'cancelled', 'comment' => 'Out of service area'])
            ->assertOk()->assertJsonPath('data.payment_status', 'refunded');
        $this->assertSame(0, Inventory::where('product_id', $p->id)->value('reserved'));
    }

    public function test_order_filters(): void
    {
        [$order] = $this->placeOrder();
        $this->actingAsStaff(Role::ADMIN);

        $this->getJson($this->api('admin/orders?status=confirmed'))->assertJsonPath('meta.total', 1);
        $this->getJson($this->api('admin/orders?status=delivered'))->assertJsonPath('meta.total', 0);
        $this->getJson($this->api('admin/orders?search='.$order['order_number']))->assertJsonPath('meta.total', 1);
        $this->getJson($this->api('admin/orders?payment_method=demo_card'))->assertJsonPath('meta.total', 0);
    }

    public function test_manual_stock_adjustments_are_logged_and_validated(): void
    {
        Event::fake([LowStockDetected::class]);
        $p = $this->product([], stock: 10);
        $inv = Inventory::where('product_id', $p->id)->first();
        $inv->update(['reserved' => 4]);
        $this->actingAsStaff(Role::INVENTORY_MANAGER);

        $this->postJson($this->api("admin/inventory/{$inv->id}/adjust"), ['type' => 'stock_received', 'quantity' => 20, 'reference' => 'PO-1001'])
            ->assertOk()->assertJsonPath('data.quantity', 30)->assertJsonPath('data.available', 26);
        $this->postJson($this->api("admin/inventory/{$inv->id}/adjust"), ['type' => 'manual_adjustment', 'quantity' => -27])
            ->assertStatus(422); // would leave less than the 4 reserved units
        $this->postJson($this->api("admin/inventory/{$inv->id}/adjust"), ['type' => 'manual_adjustment', 'quantity' => -24, 'note' => 'Damaged'])
            ->assertOk()->assertJsonPath('data.available', 2)->assertJsonPath('data.status', 'low_stock');
        $this->postJson($this->api("admin/inventory/{$inv->id}/adjust"), ['type' => 'order_shipped', 'quantity' => 1])->assertStatus(422);

        Event::assertDispatched(LowStockDetected::class);
        $this->getJson($this->api("admin/inventory/{$inv->id}"))->assertOk()->assertJsonCount(2, 'data.history');
        $this->getJson($this->api('admin/inventory?status=low_stock'))->assertJsonPath('meta.total', 1);
    }

    public function test_dashboard_and_reports_reflect_orders(): void
    {
        $this->placeOrder(qty: 2);
        $this->actingAsStaff();

        $this->getJson($this->api('admin/dashboard'))->assertOk()
            ->assertJsonPath('data.orders.total', 1)
            ->assertJsonPath('data.sales.today', 2478) // 2×1000 + ₹100 shipping + 18% GST
            ->assertJsonStructure(['data' => ['sales', 'orders', 'customers', 'products', 'inventory', 'top_products', 'top_categories', 'recent_orders', 'low_stock', 'sales_chart']]);

        foreach (['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month'] as $period) {
            $this->getJson($this->api("admin/reports/sales?period={$period}"))->assertOk()->assertJsonPath('meta.period', $period);
        }
        $this->getJson($this->api('admin/reports/sales?period=custom&from=2026-01-01&to=2026-12-31'))->assertOk();
        $this->getJson($this->api('admin/reports/sales?period=custom'))->assertStatus(422);
        $this->getJson($this->api('admin/reports/products'))->assertOk()->assertJsonPath('data.top_products.0.units', 2);
        $this->getJson($this->api('admin/reports/customers'))->assertOk();
        $this->getJson($this->api('admin/reports/inventory'))->assertOk();
    }
}
