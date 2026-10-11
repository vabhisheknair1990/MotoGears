<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->product = $this->product(['price' => 1000, 'mrp' => 1200], stock: 10);
    }

    // ── Helpers ───────────────────────────────────────────────────
    /** Adds the product to a new guest cart and returns its X-Cart-Token. */
    private function guestCart(int $qty = 2, ?Product $product = null): string
    {
        $token = $this->postJson($this->api('cart/items'), ['product_id' => ($product ?? $this->product)->id, 'quantity' => $qty])
            ->assertCreated()->json('data.token');
        $this->flushHeaders();

        return $token;
    }

    private function guestAddress(array $over = []): array
    {
        return array_merge(['name' => 'Asha Menon', 'phone' => '9876543210', 'line1' => '12 MG Road', 'city' => 'Kochi',
            'state' => 'Kerala', 'postal_code' => '682016'], $over);
    }

    private function placeGuestOrder(string $cartToken, array $over = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Cart-Token', $cartToken)->postJson($this->api('guest/orders'), array_merge([
            'email' => 'Asha@Example.com',
            'shipping_address' => $this->guestAddress(),
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
        ], $over));
    }

    private function asOrderOwner(string $token): static
    {
        return $this->flushHeaders()->withHeader('X-Order-Token', $token);
    }

    // ── Placing an order ──────────────────────────────────────────
    public function test_guest_can_check_out_without_an_account(): void
    {
        $cart = $this->guestCart(2);
        $summary = $this->withHeader('X-Cart-Token', $cart)->getJson($this->api('guest/checkout'))->assertOk();
        $summary->assertJsonPath('data.is_guest', true)->assertJsonPath('data.addresses', [])->assertJsonPath('data.cart.total_quantity', 2);

        $res = $this->placeGuestOrder($cart)->assertCreated()
            ->assertJsonPath('data.order.is_guest', true)
            ->assertJsonPath('data.order.customer.email', 'asha@example.com')
            ->assertJsonPath('data.order.customer.name', 'Asha Menon')
            ->assertJsonPath('data.order.status', 'confirmed');
        $token = $res->json('data.guest_token');
        $this->assertSame(64, strlen($token));

        $order = Order::firstOrFail();
        $this->assertNull($order->user_id);
        $this->assertTrue($order->is_guest);
        $this->assertSame('9876543210', $order->customer_phone);
        $this->assertSame(2, Inventory::where('product_id', $this->product->id)->value('reserved'));
        // Server-side prices: 2 × 1000 + 18% GST, no customer-supplied price used.
        $this->assertEquals(2000, $order->subtotal);
        // The cart is emptied and the confirmation email goes to the guest's address with the private link.
        $this->withHeader('X-Cart-Token', $cart)->getJson($this->api('cart'))->assertJsonPath('data.item_count', 0);
        Notification::assertSentOnDemand(OrderPlacedNotification::class, function ($n, $channels, AnonymousNotifiable $to) use ($token) {
            return $to->routes['mail'] === ['asha@example.com' => 'Asha Menon'] && $channels === ['mail']
                && str_contains($n->toMail($to)->actionUrl, '/order/'.$n->order->order_number.'?token='.$token);
        });
    }

    public function test_guest_order_validation(): void
    {
        $cart = $this->guestCart();
        $this->placeGuestOrder($cart, ['email' => ''])->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->placeGuestOrder($cart, ['email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->placeGuestOrder($cart, ['shipping_address' => $this->guestAddress(['postal_code' => '12'])])->assertStatus(422)->assertJsonValidationErrors(['shipping_address.postal_code']);
        $this->placeGuestOrder($cart, ['shipping_address' => null])->assertStatus(422)->assertJsonValidationErrors(['shipping_address']);
        // A token that matches no cart → empty cart, not someone else's cart.
        $this->placeGuestOrder('nope-'.str_repeat('x', 40))->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_logged_in_customers_use_the_normal_checkout(): void
    {
        $cart = $this->guestCart();
        Sanctum::actingAs($this->customer(), ['storefront']);
        $this->placeGuestOrder($cart)->assertStatus(409);
    }

    public function test_store_can_switch_guest_checkout_off(): void
    {
        Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => '0', 'type' => 'boolean', 'group' => 'checkout', 'is_public' => true]);
        $cart = $this->guestCart();
        $this->withHeader('X-Cart-Token', $cart)->getJson($this->api('guest/checkout'))->assertStatus(403)->assertJsonPath('data.guest_checkout_enabled', false);
        $this->placeGuestOrder($cart)->assertStatus(403);
        $this->getJson($this->api('settings'))->assertJsonPath('data.guest_checkout_enabled', false);
    }

    // ── Viewing & managing the order ──────────────────────────────
    public function test_only_the_secret_token_opens_a_guest_order(): void
    {
        $res = $this->placeGuestOrder($this->guestCart())->assertCreated();
        $number = $res->json('data.order.order_number');
        $token = $res->json('data.guest_token');

        $this->flushHeaders()->getJson($this->api("guest/orders/{$number}"))->assertNotFound();
        $this->asOrderOwner(str_repeat('a', 64))->getJson($this->api("guest/orders/{$number}"))->assertNotFound();
        $this->asOrderOwner($token)->getJson($this->api('guest/orders/MG000000-00000'))->assertNotFound();

        $show = $this->asOrderOwner($token)->getJson($this->api("guest/orders/{$number}"))->assertOk()
            ->assertJsonPath('data.order_number', $number)->assertJsonPath('data.can_cancel', true)->assertJsonPath('data.can_review', false);
        $this->assertStringNotContainsString($token, $show->getContent(), 'the token is never echoed back');
        $this->flushHeaders()->getJson($this->api("guest/orders/{$number}?token={$token}"))->assertOk();

        // Guest orders never show up through the account endpoints.
        Sanctum::actingAs($this->customer(['email' => 'someone@example.com']), ['storefront']);
        $this->getJson($this->api("orders/{$number}"))->assertForbidden();
    }

    public function test_guest_can_cancel_and_stock_is_released(): void
    {
        $res = $this->placeGuestOrder($this->guestCart(3))->assertCreated();
        [$number, $token] = [$res->json('data.order.order_number'), $res->json('data.guest_token')];
        $this->assertSame(3, Inventory::where('product_id', $this->product->id)->value('reserved'));

        $this->asOrderOwner($token)->postJson($this->api("guest/orders/{$number}/cancel"), ['reason' => 'Ordered the wrong size'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(0, Inventory::where('product_id', $this->product->id)->value('reserved'));
    }

    public function test_failed_payment_can_be_retried_and_invoice_downloaded(): void
    {
        $res = $this->placeGuestOrder($this->guestCart(), [
            'payment_method' => 'demo_card',
            'payment_details' => ['card_number' => '4000 0000 0000 0002', 'card_name' => 'Asha', 'expiry' => '12/29', 'cvv' => '123'],
        ])->assertCreated()->assertJsonPath('data.payment_successful', false);
        [$number, $token] = [$res->json('data.order.order_number'), $res->json('data.guest_token')];

        $this->asOrderOwner($token)->postJson($this->api("guest/orders/{$number}/pay"), ['payment_method' => 'demo_upi', 'payment_details' => ['upi_id' => 'asha@okaxis']])
            ->assertOk()->assertJsonPath('data.payment_successful', true)->assertJsonPath('data.order.payment_status', 'paid');

        $this->asOrderOwner($token)->getJson($this->api("guest/orders/{$number}/invoice"))->assertOk()
            ->assertJsonPath('data.customer.email', 'asha@example.com');
        $this->asOrderOwner($token)->get($this->api("guest/orders/{$number}/invoice/download"))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="invoice-'.$number.'.html"');
    }

    public function test_guest_can_pay_with_razorpay(): void
    {
        config(['payments.razorpay.key_id' => 'rzp_test_KEY', 'payments.razorpay.key_secret' => 'sek', 'payments.razorpay.base_url' => 'https://api.razorpay.test/v1']);
        Http::fake(function (HttpRequest $req) {
            $path = parse_url($req->url(), PHP_URL_PATH);
            if ($req->method() === 'POST' && str_ends_with($path, '/v1/orders')) {
                return Http::response(['id' => 'order_G1', 'amount' => $req['amount'], 'currency' => 'INR', 'status' => 'created']);
            }
            $p = Payment::latest('id')->first();

            return Http::response(['id' => 'pay_G1', 'status' => 'captured', 'method' => 'upi', 'order_id' => 'order_G1', 'amount' => (int) round($p->amount * 100), 'currency' => 'INR']);
        });

        $res = $this->placeGuestOrder($this->guestCart(), ['payment_method' => 'razorpay'])->assertCreated()
            ->assertJsonPath('data.razorpay.order_id', 'order_G1')
            ->assertJsonPath('data.razorpay.prefill.email', 'asha@example.com')
            ->assertJsonPath('data.razorpay.prefill.name', 'Asha Menon');
        [$number, $token] = [$res->json('data.order.order_number'), $res->json('data.guest_token')];

        $this->asOrderOwner($token)->postJson($this->api("guest/orders/{$number}/razorpay/verify"), [
            'razorpay_order_id' => 'order_G1', 'razorpay_payment_id' => 'pay_G1',
            'razorpay_signature' => hash_hmac('sha256', 'order_G1|pay_G1', 'sek'),
        ])->assertOk()->assertJsonPath('data.order.status', 'confirmed');
    }

    // ── Track order ───────────────────────────────────────────────
    public function test_track_order_with_number_and_email(): void
    {
        $res = $this->placeGuestOrder($this->guestCart())->assertCreated();
        [$number, $token] = [$res->json('data.order.order_number'), $res->json('data.guest_token')];

        $this->postJson($this->api('guest/orders/track'), ['order_number' => strtolower($number), 'email' => ' ASHA@example.com '])
            ->assertOk()->assertJsonPath('data.token', $token)->assertJsonPath('data.order_number', $number);
        $this->postJson($this->api('guest/orders/track'), ['order_number' => $number, 'email' => 'other@example.com'])->assertNotFound();
        $this->postJson($this->api('guest/orders/track'), ['order_number' => 'MG000000-00000', 'email' => 'asha@example.com'])->assertNotFound();

        // An account order points the customer to log in instead.
        $user = $this->customer(['email' => 'reg@example.com']);
        $o = Order::firstOrFail()->replicate(['guest_token']);
        $o->fill(['order_number' => 'MG990101-12345', 'user_id' => $user->id, 'is_guest' => false, 'customer_email' => 'reg@example.com'])->save();
        $this->postJson($this->api('guest/orders/track'), ['order_number' => 'MG990101-12345', 'email' => 'reg@example.com'])
            ->assertStatus(409)->assertJsonPath('data.requires_login', true);
    }

    public function test_track_order_is_rate_limited(): void
    {
        foreach (range(1, 6) as $i) {
            $this->postJson($this->api('guest/orders/track'), ['order_number' => 'MG000000-0000'.$i, 'email' => 'x@example.com'])->assertNotFound();
        }
        $this->postJson($this->api('guest/orders/track'), ['order_number' => 'MG000000-00009', 'email' => 'x@example.com'])->assertStatus(429);
    }

    // ── Coupons ───────────────────────────────────────────────────
    public function test_per_customer_coupon_limit_applies_to_the_guest_email(): void
    {
        Coupon::factory()->create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 100, 'per_user_limit' => 1, 'min_order_amount' => 0]);

        $cart = $this->guestCart();
        $this->withHeader('X-Cart-Token', $cart)->postJson($this->api('cart/apply-coupon'), ['code' => 'ONCE'])->assertOk();
        $this->placeGuestOrder($cart)->assertCreated()->assertJsonPath('data.order.discount', 100);

        $cart2 = $this->guestCart();
        $this->withHeader('X-Cart-Token', $cart2)->postJson($this->api('cart/apply-coupon'), ['code' => 'ONCE'])->assertOk();
        $this->placeGuestOrder($cart2, ['email' => 'asha@example.com'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'maximum number of times'));
        // …and the same person can't reuse it after creating an account with that email.
        $this->assertDatabaseHas('coupon_usages', ['email' => 'asha@example.com', 'user_id' => null]);
    }

    // ── Accounts ──────────────────────────────────────────────────
    public function test_guest_can_turn_the_order_into_an_account(): void
    {
        $res = $this->placeGuestOrder($this->guestCart())->assertCreated();
        [$number, $token] = [$res->json('data.order.order_number'), $res->json('data.guest_token')];

        $this->asOrderOwner($token)->postJson($this->api("guest/orders/{$number}/create-account"), ['password' => 'short', 'password_confirmation' => 'short'])->assertStatus(422);
        $acc = $this->asOrderOwner($token)->postJson($this->api("guest/orders/{$number}/create-account"), [
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
        ])->assertCreated();
        $this->assertNotEmpty($acc->json('data.token'));

        $user = User::where('email', 'asha@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::CUSTOMER));
        $order = Order::where('order_number', $number)->firstOrFail();
        $this->assertSame($user->id, $order->user_id);
        $this->assertFalse($order->is_guest);
        $this->assertSame('682016', $user->addresses()->first()->postal_code);

        // The new account sees the order under My orders; doing it twice is refused.
        $this->flushHeaders()->withHeader('Authorization', 'Bearer '.$acc->json('data.token'))
            ->getJson($this->api('me/orders'))->assertOk()->assertJsonPath('data.0.order_number', $number);
        $this->asOrderOwner($token)->postJson($this->api("guest/orders/{$number}/create-account"), ['password' => 'Secret123', 'password_confirmation' => 'Secret123'])->assertStatus(409);
    }

    public function test_existing_customer_adds_a_guest_order_to_their_account(): void
    {
        $res = $this->placeGuestOrder($this->guestCart())->assertCreated();
        [$number, $token] = [$res->json('data.order.order_number'), $res->json('data.guest_token')];

        // An account already exists for that email → create-account explains to log in instead.
        $owner = $this->customer(['email' => 'asha@example.com']);
        $this->asOrderOwner($token)->postJson($this->api("guest/orders/{$number}/create-account"), ['password' => 'Secret123', 'password_confirmation' => 'Secret123'])
            ->assertStatus(409);

        // Someone else's account can't take it, even with the link.
        Sanctum::actingAs($this->customer(['email' => 'mallory@example.com']), ['storefront']);
        $this->withHeader('X-Order-Token', $token)->postJson($this->api("guest/orders/{$number}/attach"))->assertStatus(422);

        Sanctum::actingAs($owner, ['storefront']);
        $this->withHeader('X-Order-Token', $token)->postJson($this->api("guest/orders/{$number}/attach"))->assertOk()->assertJsonPath('data.is_guest', false);
        $this->getJson($this->api("orders/{$number}"))->assertOk();
    }

    // ── Admin ─────────────────────────────────────────────────────
    public function test_admin_sees_and_filters_guest_orders(): void
    {
        $this->placeGuestOrder($this->guestCart())->assertCreated();
        $this->actingAsStaff(Role::SUPER_ADMIN);

        $this->getJson($this->api('admin/orders?customer_type=guest'))->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('meta.guest_count', 1)
            ->assertJsonPath('data.0.is_guest', true)->assertJsonPath('data.0.customer.email', 'asha@example.com')->assertJsonPath('data.0.customer.id', null);
        $this->getJson($this->api('admin/orders?customer_type=registered'))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($this->api('admin/orders?search=asha@example'))->assertOk()->assertJsonPath('meta.total', 1);

        $id = Order::first()->id;
        $this->patchJson($this->api("admin/orders/{$id}/status"), ['status' => 'processing'])->assertOk();
    }

    public function test_browsers_may_send_the_order_token_header_cross_origin(): void
    {
        $preflight = $this->call('OPTIONS', '/v1/guest/orders/MG000000-00000', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:4200',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-order-token',
        ]);

        $preflight->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4200');
        $this->assertStringContainsStringIgnoringCase('x-order-token', (string) $preflight->headers->get('Access-Control-Allow-Headers'));
    }
}
