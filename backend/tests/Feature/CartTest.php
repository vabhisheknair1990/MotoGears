<?php

namespace Tests\Feature;

use App\Models\Cart;
use Tests\TestCase;

class CartTest extends TestCase
{
    public function test_guest_can_build_a_cart_with_a_token(): void
    {
        $p = $this->product(['price' => 1000, 'mrp' => 1200]);

        $res = $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => 2])
            ->assertCreated()
            ->assertHeader('X-Cart-Token')
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.subtotal', 2000);
        $token = $res->json('data.token');

        $this->withHeader('X-Cart-Token', $token)->getJson($this->api('cart'))->assertJsonPath('data.total_quantity', 2);
        $this->flushHeaders()->getJson($this->api('cart'))->assertJsonPath('data.item_count', 0); // no token → empty cart
    }

    public function test_totals_are_calculated_server_side(): void
    {
        $p = $this->product(['price' => 2500, 'mrp' => 3000, 'tax_rate' => 18]);
        $this->actingAsCustomer();

        // Client-supplied prices are ignored.
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => 2, 'price' => 1])
            ->assertJsonPath('data.subtotal', 5000)
            ->assertJsonPath('data.shipping', 0)          // above the ₹2,999 free-shipping threshold
            ->assertJsonPath('data.tax', 900)
            ->assertJsonPath('data.grand_total', 5900)
            ->assertJsonPath('data.savings', 1000);
    }

    public function test_shipping_and_tax_below_free_shipping_threshold(): void
    {
        $p = $this->product(['price' => 1000, 'mrp' => 1000, 'tax_rate' => 18]);
        $this->actingAsCustomer();

        $this->postJson($this->api('cart/items'), ['product_id' => $p->id])
            ->assertJsonPath('data.shipping', 100)
            ->assertJsonPath('data.tax', 198)             // 18% of (1000 + 100 shipping)
            ->assertJsonPath('data.grand_total', 1298);
    }

    public function test_cannot_exceed_available_stock(): void
    {
        $p = $this->product([], stock: 3);
        $this->actingAsCustomer();

        $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => 4])->assertStatus(422)->assertJsonPath('success', false);
        $item = $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => 3])->assertCreated()->json('data.items.0.id');
        $this->patchJson($this->api("cart/items/{$item}"), ['quantity' => 5])->assertStatus(422);
    }

    public function test_update_remove_and_clear(): void
    {
        $a = $this->product();
        $b = $this->product();
        $this->actingAsCustomer();

        $itemA = $this->postJson($this->api('cart/items'), ['product_id' => $a->id])->json('data.items.0.id');
        $this->postJson($this->api('cart/items'), ['product_id' => $b->id]);
        $this->patchJson($this->api("cart/items/{$itemA}"), ['quantity' => 3])->assertOk()->assertJsonPath('data.total_quantity', 4);
        $this->deleteJson($this->api("cart/items/{$itemA}"))->assertOk()->assertJsonPath('data.item_count', 1);
        $this->deleteJson($this->api('cart'))->assertOk()->assertJsonPath('data.item_count', 0);
    }

    public function test_cannot_modify_someone_elses_cart_item(): void
    {
        $p = $this->product();
        $this->actingAsCustomer();
        $item = $this->postJson($this->api('cart/items'), ['product_id' => $p->id])->json('data.items.0.id');

        $this->actingAsCustomer();
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id]);
        $this->patchJson($this->api("cart/items/{$item}"), ['quantity' => 2])->assertNotFound();
    }

    public function test_guest_cart_merges_into_user_cart_on_login(): void
    {
        $p = $this->product();
        $q = $this->product();
        $user = $this->customer(['email' => 'merge@example.com']);
        Cart::create(['user_id' => $user->id])->items()->create(['product_id' => $p->id, 'quantity' => 1]);

        $token = $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => 2])->json('data.token');
        $this->postJson($this->api('cart/items'), ['product_id' => $q->id], ['X-Cart-Token' => $token]);

        $bearer = $this->postJson($this->api('auth/login'), ['email' => 'merge@example.com', 'password' => 'password'], ['X-Cart-Token' => $token])->json('data.token');

        $this->withToken($bearer)->getJson($this->api('cart'))
            ->assertJsonPath('data.item_count', 2)
            ->assertJsonPath('data.total_quantity', 4);
        $this->assertDatabaseMissing('carts', ['token' => $token]);
    }

    public function test_wishlist_add_remove_and_move_to_cart(): void
    {
        $p = $this->product();
        $this->actingAsCustomer();

        $this->postJson($this->api('wishlist/items'), ['product_id' => $p->id])->assertCreated()->assertJsonPath('data.count', 1);
        $this->postJson($this->api('wishlist/items'), ['product_id' => $p->id])->assertJsonPath('data.count', 1); // idempotent
        $this->postJson($this->api("wishlist/items/{$p->id}/move-to-cart"))->assertOk()->assertJsonPath('data.count', 0);
        $this->getJson($this->api('cart'))->assertJsonPath('data.item_count', 1);

        $this->postJson($this->api('wishlist/items'), ['product_id' => $p->id]);
        $this->deleteJson($this->api("wishlist/items/{$p->id}"))->assertOk()->assertJsonPath('data.count', 0);
    }
}
