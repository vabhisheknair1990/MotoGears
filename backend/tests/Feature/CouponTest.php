<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use Tests\TestCase;

class CouponTest extends TestCase
{
    private function cartWith(float $price, int $qty = 1, array $attrs = []): void
    {
        $p = $this->product(array_merge(['price' => $price, 'mrp' => $price], $attrs));
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => $qty])->assertCreated();
    }

    public function test_percentage_coupon_respects_max_discount(): void
    {
        $this->actingAsCustomer();
        Coupon::factory()->create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10, 'max_discount' => 300]);
        $this->cartWith(5000);

        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'welcome10'])
            ->assertOk()
            ->assertJsonPath('data.coupon.code', 'WELCOME10')
            ->assertJsonPath('data.discount', 300);
    }

    public function test_fixed_coupon_requires_minimum_order(): void
    {
        $this->actingAsCustomer();
        Coupon::factory()->create(['code' => 'SAVE500', 'type' => 'fixed', 'value' => 500, 'min_order_amount' => 4999]);
        $this->cartWith(3000);

        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'SAVE500'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->cartWith(2500);
        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'SAVE500'])->assertOk()->assertJsonPath('data.discount', 500);
    }

    public function test_free_shipping_coupon(): void
    {
        $this->actingAsCustomer();
        Coupon::factory()->create(['code' => 'FREESHIP', 'type' => 'free_shipping', 'value' => 0]);
        $this->cartWith(500);
        $this->getJson($this->api('cart'))->assertJsonPath('data.shipping', 100);

        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'FREESHIP'])
            ->assertOk()->assertJsonPath('data.shipping', 0)->assertJsonPath('data.discount', 0);
    }

    public function test_expired_inactive_and_unknown_coupons_are_rejected(): void
    {
        $this->actingAsCustomer();
        $this->cartWith(1000);
        Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subDay()]);
        Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);
        Coupon::factory()->create(['code' => 'SOON', 'starts_at' => now()->addDay()]);

        foreach (['OLD' => 'expired', 'OFF' => 'no longer active', 'SOON' => 'not active yet', 'NOPE' => 'not valid'] as $code => $msg) {
            $res = $this->postJson($this->api('cart/apply-coupon'), ['code' => $code])->assertStatus(422);
            $this->assertStringContainsString($msg, $res->json('message'));
        }
    }

    public function test_usage_limits(): void
    {
        $user = $this->actingAsCustomer();
        $this->cartWith(1000);
        $limited = Coupon::factory()->create(['code' => 'ONCE', 'usage_limit' => 5, 'used_count' => 5]);
        $perUser = Coupon::factory()->create(['code' => 'MINE', 'per_user_limit' => 1]);
        $order = Order::create(['order_number' => 'X1', 'user_id' => $user->id, 'status' => 'delivered', 'payment_method' => 'cod', 'subtotal' => 1, 'grand_total' => 1, 'billing_address' => [], 'shipping_address' => []]);
        CouponUsage::create(['coupon_id' => $perUser->id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount_amount' => 10]);

        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'ONCE'])->assertStatus(422);
        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'MINE'])->assertStatus(422);
    }

    public function test_category_restricted_coupon_only_discounts_eligible_items(): void
    {
        $this->actingAsCustomer();
        $brakes = Category::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'BRAKES15', 'type' => 'percentage', 'value' => 15]);
        $coupon->categories()->attach($brakes->id);

        $this->cartWith(1000);
        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'BRAKES15'])->assertStatus(422);

        $this->cartWith(2000, 1, ['category_id' => $brakes->id]);
        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'BRAKES15'])
            ->assertOk()->assertJsonPath('data.discount', 300)->assertJsonPath('data.subtotal', 3000);
    }

    public function test_remove_coupon(): void
    {
        $this->actingAsCustomer();
        Coupon::factory()->create(['code' => 'TENOFF']);
        $this->cartWith(1000);
        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'TENOFF'])->assertOk();

        $this->deleteJson($this->api('cart/coupon'))->assertOk()->assertJsonPath('data.coupon', null)->assertJsonPath('data.discount', 0);
    }
}
