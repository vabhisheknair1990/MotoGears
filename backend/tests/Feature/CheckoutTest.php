<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->user = $this->actingAsCustomer();
    }

    private function fillCart(int $qty = 2, int $stock = 10, float $price = 1500): \App\Models\Product
    {
        $p = $this->product(['price' => $price, 'mrp' => $price + 500], stock: $stock);
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => $qty])->assertCreated();

        return $p;
    }

    private function order(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $address = $this->address($this->user);

        return $this->postJson($this->api('orders'), array_merge([
            'shipping_address_id' => $address->id,
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
        ], $overrides));
    }

    public function test_checkout_summary(): void
    {
        $this->fillCart();
        $this->address($this->user);

        $this->getJson($this->api('checkout'))->assertOk()
            ->assertJsonStructure(['data' => ['cart' => ['items', 'grand_total'], 'addresses', 'shipping_methods', 'payment_methods']])
            ->assertJsonCount(2, 'data.shipping_methods')
            ->assertJsonCount(3, 'data.payment_methods');
    }

    public function test_cash_on_delivery_order_reserves_stock_and_clears_cart(): void
    {
        $p = $this->fillCart(qty: 2, stock: 10);

        $res = $this->order()->assertCreated()
            ->assertJsonPath('data.order.status', 'confirmed')
            ->assertJsonPath('data.order.payment_status', 'pending')
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.order.subtotal', 3000)
            ->assertJsonPath('data.order.shipping', 0)
            ->assertJsonPath('data.order.tax', 540)
            ->assertJsonPath('data.order.grand_total', 3540)
            ->assertJsonStructure(['data' => ['order' => ['order_number', 'items', 'timeline', 'shipping_address', 'billing_address', 'payment']]]);

        $inv = Inventory::where('product_id', $p->id)->first();
        $this->assertSame(10, $inv->quantity);
        $this->assertSame(2, $inv->reserved);
        $this->assertSame(8, $inv->available());
        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $p->id, 'type' => 'order_reserved', 'quantity' => 2]);
        $this->getJson($this->api('cart'))->assertJsonPath('data.item_count', 0);

        $number = $res->json('data.order.order_number');
        $this->getJson($this->api("orders/{$number}"))->assertOk()->assertJsonPath('data.order_number', $number);
        $this->getJson($this->api('me/orders'))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_demo_card_success_and_decline_then_retry(): void
    {
        $this->fillCart();
        $declined = $this->order([
            'payment_method' => 'demo_card',
            'payment_details' => ['card_number' => '4000 0000 0000 0002', 'card_name' => 'Test', 'expiry' => '12/40', 'cvv' => '123'],
        ])->assertCreated()
            ->assertJsonPath('data.payment_successful', false)
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.payment_status', 'failed')
            ->assertJsonPath('data.order.can_pay', true);
        $id = $declined->json('data.order.id');

        $this->postJson($this->api("orders/{$id}/pay"), ['payment_method' => 'demo_upi', 'payment_details' => ['upi_id' => 'failure@upi']])
            ->assertStatus(402)->assertJsonPath('data.order.payment_status', 'failed');

        $this->postJson($this->api("orders/{$id}/pay"), [
            'payment_method' => 'demo_card',
            'payment_details' => ['card_number' => '4111 1111 1111 1111', 'card_name' => 'Test', 'expiry' => '12/40', 'cvv' => '123'],
        ])->assertOk()
            ->assertJsonPath('data.order.status', 'confirmed')
            ->assertJsonPath('data.order.payment_status', 'paid')
            ->assertJsonPath('data.payment.details.last4', '1111');

        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseMissing('payments', ['meta' => '%4111111111111111%']);
    }

    public function test_card_details_are_validated(): void
    {
        $this->fillCart();
        $this->order(['payment_method' => 'demo_card', 'payment_details' => ['card_number' => 'abc']])
            ->assertStatus(422)->assertJsonValidationErrors(['payment_details.card_number', 'payment_details.cvv']);
    }

    public function test_cannot_order_more_than_available_stock(): void
    {
        $p = $this->fillCart(qty: 3, stock: 3);
        // Someone else buys two units in the meantime.
        Inventory::where('product_id', $p->id)->update(['reserved' => 2]);

        $this->order()->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, Inventory::where('product_id', $p->id)->value('reserved'));
    }

    public function test_empty_cart_cannot_checkout(): void
    {
        $this->order()->assertStatus(422)->assertJsonPath('message', 'Your cart is empty.');
    }

    public function test_address_must_belong_to_user(): void
    {
        $this->fillCart();
        $other = $this->address($this->customer());

        $this->postJson($this->api('orders'), ['shipping_address_id' => $other->id, 'shipping_method' => 'standard', 'payment_method' => 'cod'])
            ->assertStatus(422);
    }

    public function test_inline_address_can_be_saved(): void
    {
        $this->fillCart();
        $this->postJson($this->api('orders'), [
            'shipping_address' => ['name' => 'A B', 'phone' => '9876543210', 'line1' => '1 Road', 'city' => 'Pune', 'state' => 'Maharashtra', 'postal_code' => '411001'],
            'save_address' => true, 'shipping_method' => 'express', 'payment_method' => 'cod',
        ])->assertCreated()->assertJsonPath('data.order.shipping_address.city', 'Pune')->assertJsonPath('data.order.shipping', 250);
        $this->assertDatabaseHas('addresses', ['user_id' => $this->user->id, 'city' => 'Pune']);
    }

    public function test_coupon_is_recorded_and_released_on_cancel(): void
    {
        $p = $this->fillCart(qty: 2, stock: 5);
        Coupon::factory()->create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10]);
        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'TEN'])->assertOk();

        $order = $this->order(['payment_method' => 'demo_upi', 'payment_details' => ['upi_id' => 'me@okaxis']])
            ->assertCreated()->assertJsonPath('data.order.discount', 300)->assertJsonPath('data.order.coupon_code', 'TEN')->json('data.order');
        $this->assertSame(1, Coupon::where('code', 'TEN')->value('used_count'));

        $this->postJson($this->api("orders/{$order['id']}/cancel"), ['reason' => 'Changed my mind'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.payment_status', 'refunded');

        $this->assertSame(0, Coupon::where('code', 'TEN')->value('used_count'));
        $this->assertSame(0, Inventory::where('product_id', $p->id)->value('reserved'));
        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $p->id, 'type' => 'order_cancelled']);
    }

    public function test_shipped_orders_cannot_be_cancelled_by_customer(): void
    {
        $this->fillCart();
        $id = $this->order()->json('data.order.id');
        Order::whereKey($id)->update(['status' => 'shipped']);

        $this->postJson($this->api("orders/{$id}/cancel"))->assertStatus(422);
    }

    public function test_invoice_json_and_download(): void
    {
        $this->fillCart();
        $id = $this->order()->json('data.order.id');

        $this->getJson($this->api("orders/{$id}/invoice"))->assertOk()
            ->assertJsonStructure(['data' => ['invoice_number', 'seller', 'items', 'totals', 'billing_address']]);
        $this->get($this->api("orders/{$id}/invoice/download"))->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('TAX INVOICE');
    }
}
