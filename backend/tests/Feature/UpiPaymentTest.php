<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UpiPaymentTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->user = $this->actingAsCustomer();
    }

    private function placeUpiOrder(int $qty = 1, float $price = 1000): array
    {
        $p = $this->product(['price' => $price, 'mrp' => $price], stock: 10);
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => $qty])->assertCreated();
        $res = $this->postJson($this->api('orders'), [
            'shipping_address_id' => $this->address($this->user)->id, 'shipping_method' => 'standard', 'payment_method' => 'upi',
        ])->assertCreated();

        return [$res->json('data.order'), $res, $p];
    }

    private function useRazorpay(): void
    {
        config(['payments.razorpay.key_id' => 'rzp_test_KEY', 'payments.razorpay.key_secret' => 'sek',
            'payments.razorpay.webhook_secret' => 'whsec', 'payments.razorpay.base_url' => 'https://api.razorpay.test/v1']);
    }

    // ── Offering UPI ──────────────────────────────────────────────
    public function test_upi_is_offered_first_and_order_waits_for_payment(): void
    {
        $this->product(); // something in the catalogue
        $methods = collect($this->getJson($this->api('settings'))->json('data.payment_methods'))->pluck('code');
        $this->assertSame('upi', $methods->first());
        $this->assertNotContains('demo_upi', $methods); // the old simulated UPI is replaced

        [$order, $res] = $this->placeUpiOrder();
        $res->assertJsonPath('data.payment_successful', false)
            ->assertJsonPath('data.upi.available', true)
            ->assertJsonPath('data.upi.demo', true)
            ->assertJsonPath('data.upi.amount', $order['grand_total'])
            ->assertJsonPath('data.razorpay', null);
        $this->assertSame('pending', $order['status']);
        $this->assertSame('pending', $order['payment_status']);
        $this->assertTrue($order['can_pay']);

        config(['payments.upi.enabled' => false]);
        $this->assertNotContains('upi', collect($this->getJson($this->api('settings'))->json('data.payment_methods'))->pluck('code'));
    }

    // ── QR code ───────────────────────────────────────────────────
    public function test_qr_code_payment_confirms_the_order(): void
    {
        [$order] = $this->placeUpiOrder(price: 1000);
        $qr = $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.flow', 'intent')->assertJsonPath('data.demo', true);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr->json('data.qr'));
        $this->assertStringStartsWith('upi://pay?pa=motogears%40demo', $qr->json('data.link'));
        $this->assertStringContainsString('am='.number_format($order['grand_total'], 2, '.', ''), $qr->json('data.link'));
        $this->assertGreaterThan(500, $qr->json('data.expires_in'));

        // Opening the page again shows the same QR while it is valid.
        $this->assertSame($qr->json('data.link'), $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->json('data.link'));
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonMissingPath('data.order');

        $this->postJson($this->api("orders/{$order['id']}/upi/simulate"), ['outcome' => 'success'])->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.order.status', 'confirmed')
            ->assertJsonPath('data.order.payment_status', 'paid');

        $payment = Payment::where('order_id', $order['id'])->sole();
        $this->assertSame('success', $payment->status->value);
        $this->assertStringStartsWith('UPI', $payment->transaction_id);
        $this->assertSame('upi_status', $payment->meta['confirmed_via']);
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'paid');
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertStatus(409);
        $this->getJson($this->api("orders/{$order['id']}"))->assertJsonPath('data.payment.details.upi_flow', 'intent');
    }

    public function test_expired_qr_can_be_replaced(): void
    {
        [$order] = $this->placeUpiOrder();
        $first = $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->json('data.link');

        $this->travel(11)->minutes();
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.message', 'This QR code expired.');
        $second = $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk()->assertJsonPath('data.status', 'pending')->json('data.link');
        $this->assertSame($first, $second, 'Same payee/amount/order reference; only the window is renewed');
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'pending');
    }

    // ── UPI ID (collect) ──────────────────────────────────────────
    public function test_collect_request_to_upi_id_is_approved(): void
    {
        [$order] = $this->placeUpiOrder();
        $this->postJson($this->api("orders/{$order['id']}/upi/collect"), ['vpa' => 'not-a-vpa'])->assertUnprocessable()->assertJsonValidationErrors('vpa');
        $this->postJson($this->api("orders/{$order['id']}/upi/collect"), ['vpa' => 'invalid@okaxis'])->assertUnprocessable()
            ->assertJsonPath('message', 'This UPI ID could not be found. Please check it and try again.');

        $this->postJson($this->api("orders/{$order['id']}/upi/collect"), ['vpa' => ' Asha.M@OKAXIS '])->assertOk()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.flow', 'collect')
            ->assertJsonPath('data.vpa', 'as****@okaxis')->assertJsonPath('data.payer_name', 'Demo Customer')
            ->assertJsonPath('data.expires_in', fn ($s) => $s >= 295 && $s <= 300); // 5-minute request window
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'pending');

        $this->travel(5)->seconds(); // the customer approves in their UPI app
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertOk()
            ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.order.status', 'confirmed');
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order['id'], 'status' => 'confirmed']);
    }

    public function test_declined_request_can_be_retried_with_a_qr_code(): void
    {
        [$order] = $this->placeUpiOrder();
        $this->postJson($this->api("orders/{$order['id']}/upi/collect"), ['vpa' => 'fail@ybl'])->assertOk();
        $this->travel(5)->seconds();
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.message', 'The UPI request was declined by the payer (demo).')
            ->assertJsonPath('data.order.payment_status', 'failed')
            ->assertJsonPath('data.order.status', 'pending');

        // A new attempt starts automatically and the stock is still reserved.
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk()->assertJsonPath('data.status', 'pending');
        $this->assertSame(2, Payment::where('order_id', $order['id'])->count());
        $this->postJson($this->api("orders/{$order['id']}/upi/simulate"), ['outcome' => 'success'])->assertJsonPath('data.status', 'paid');
        $this->assertSame(1, Payment::where('order_id', $order['id'])->where('status', 'success')->count());
    }

    public function test_switching_from_another_method_to_upi(): void
    {
        $p = $this->product(['price' => 1000, 'mrp' => 1000]);
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => 1]);
        $order = $this->postJson($this->api('orders'), [
            'shipping_address_id' => $this->address($this->user)->id, 'shipping_method' => 'standard', 'payment_method' => 'demo_card',
            'payment_details' => ['card_number' => '4000 0000 0000 0002', 'card_name' => 'T', 'expiry' => '12/40', 'cvv' => '123'],
        ])->assertCreated()->json('data.order');

        $this->postJson($this->api("orders/{$order['id']}/pay"), ['payment_method' => 'upi'])->assertOk()
            ->assertJsonPath('data.upi.available', true)->assertJsonPath('data.payment_successful', false)
            ->assertJsonPath('data.order.payment_method', 'upi');
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk();
        $this->postJson($this->api("orders/{$order['id']}/upi/simulate"), ['outcome' => 'success'])->assertJsonPath('data.status', 'paid');
    }

    // ── Access & state rules ──────────────────────────────────────
    public function test_only_the_owner_can_pay_and_closed_orders_cannot(): void
    {
        [$order] = $this->placeUpiOrder();
        $this->actingAsCustomer($this->customer());
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertForbidden();
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertForbidden();

        $this->actingAsCustomer($this->user);
        $this->postJson($this->api("orders/{$order['id']}/cancel"))->assertOk();
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertUnprocessable();
        $this->postJson($this->api("orders/{$order['id']}/upi/simulate"), ['outcome' => 'success'])->assertNotFound();
    }

    public function test_guest_pays_by_upi_with_the_order_token(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $p = $this->product(['price' => 800, 'mrp' => 800]);
        $cart = $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => 1])->json('data.token');
        $res = $this->withHeader('X-Cart-Token', $cart)->postJson($this->api('guest/orders'), [
            'email' => 'ravi@example.com', 'shipping_method' => 'standard', 'payment_method' => 'upi',
            'shipping_address' => ['name' => 'Ravi', 'phone' => '9876543210', 'line1' => '1 Road', 'city' => 'Kochi', 'state' => 'Kerala', 'postal_code' => '682011'],
        ])->assertCreated()->assertJsonPath('data.upi.demo', true);
        [$number, $token] = [$res->json('data.order.order_number'), $res->json('data.guest_token')];

        $this->flushHeaders()->postJson($this->api("guest/orders/{$number}/upi/qr"))->assertNotFound();
        $this->withHeader('X-Order-Token', $token)->postJson($this->api("guest/orders/{$number}/upi/qr"))->assertOk()->assertJsonPath('data.status', 'pending');
        $this->getJson($this->api("guest/orders/{$number}/upi/status?token={$token}"))->assertOk()->assertJsonPath('data.status', 'pending');
        $this->withHeader('X-Order-Token', $token)->postJson($this->api("guest/orders/{$number}/upi/simulate"), ['outcome' => 'success'])
            ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.order.is_guest', true);
    }

    // ── Razorpay (real provider, HTTP faked) ──────────────────────
    public function test_razorpay_qr_is_confirmed_by_polling(): void
    {
        $this->useRazorpay();
        $sent = [];
        Http::fake(function (HttpRequest $req) use (&$sent) {
            $path = parse_url($req->url(), PHP_URL_PATH);
            $sent[] = $req->method().' '.$path;
            $paise = (int) round(Payment::latest('id')->first()->amount * 100);

            return match (true) {
                $path === '/v1/orders' => Http::response(['id' => 'order_U1', 'amount' => $req['amount'], 'status' => 'created']),
                $path === '/v1/payments/create/upi' => Http::response(['razorpay_payment_id' => 'pay_Q1', 'link' => 'upi://pay?pa=rzp@icici&am=1&tr=x']),
                $path === '/v1/orders/order_U1/payments' => Http::response(['items' => [['id' => 'pay_Q1', 'status' => 'captured', 'method' => 'upi', 'vpa' => 'asha@okicici', 'amount' => $paise, 'order_id' => 'order_U1']]]),
                str_ends_with($path, '/refund') => Http::response(['id' => 'rfnd_1', 'status' => 'processed']),
                default => Http::response(['error' => ['description' => 'unexpected']], 500),
            };
        });

        [$order, $res] = $this->placeUpiOrder();
        $res->assertJsonPath('data.upi.demo', false);
        $this->assertSame('order_U1', Payment::latest('id')->first()->gateway_order_id);

        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk()
            ->assertJsonPath('data.link', 'upi://pay?pa=rzp@icici&am=1&tr=x')->assertJsonPath('data.demo', false);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/create/upi')
            && $r['upi'] === ['flow' => 'intent'] && $r['order_id'] === 'order_U1' && $r['method'] === 'upi' && $r['contact'] && $r['email']);

        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.transaction_id', 'pay_Q1');
        $this->assertSame('asha@okicici', Payment::latest('id')->first()->meta['vpa']);
        $this->assertContains('GET /v1/orders/order_U1/payments', $sent);

        // Cancelling the paid order refunds through Razorpay.
        $this->postJson($this->api("orders/{$order['id']}/cancel"))->assertOk()->assertJsonPath('data.payment_status', 'refunded');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/pay_Q1/refund'));
    }

    public function test_razorpay_collect_request_and_decline(): void
    {
        $this->useRazorpay();
        Http::fake(function (HttpRequest $req) {
            $path = parse_url($req->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/v1/orders' => Http::response(['id' => 'order_C1', 'status' => 'created']),
                $path === '/v1/payments/validate/vpa' => Http::response(['vpa' => $req['vpa'], 'success' => true, 'customer_name' => 'Asha Menon']),
                $path === '/v1/payments/create/upi' => Http::response(['razorpay_payment_id' => 'pay_C1']),
                $path === '/v1/orders/order_C1/payments' => Http::response(['items' => [['id' => 'pay_C1', 'status' => 'failed', 'error_description' => 'Payment was declined by the customer', 'amount' => 1, 'order_id' => 'order_C1']]]),
                default => Http::response([], 500),
            };
        });

        [$order] = $this->placeUpiOrder();
        $this->postJson($this->api("orders/{$order['id']}/upi/collect"), ['vpa' => 'asha@okhdfcbank'])->assertOk()
            ->assertJsonPath('data.payer_name', 'Asha Menon')->assertJsonPath('data.vpa', 'as**@okhdfcbank');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/create/upi')
            && $r['upi'] === ['flow' => 'collect', 'vpa' => 'asha@okhdfcbank', 'expiry_time' => 5]);

        $this->getJson($this->api("orders/{$order['id']}/upi/status"))
            ->assertJsonPath('data.status', 'failed')->assertJsonPath('data.message', 'Payment was declined by the customer');
    }

    public function test_razorpay_webhook_confirms_a_upi_payment_when_the_page_is_closed(): void
    {
        $this->useRazorpay();
        Http::fake(['*' => fn (HttpRequest $req) => Http::response(['id' => 'order_W1', 'razorpay_payment_id' => 'pay_W1', 'link' => 'upi://pay?pa=a@b'])]);
        [$order] = $this->placeUpiOrder();
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk();

        $paise = (int) round(Order::find($order['id'])->grand_total * 100);
        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_W1', 'order_id' => 'order_W1', 'amount' => $paise, 'status' => 'captured', 'method' => 'upi', 'vpa' => 'x@ybl',
        ]]]]);
        $this->call('POST', $this->api('webhooks/razorpay'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec')], $body)
            ->assertOk()->assertJsonPath('data.result', 'paid');

        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'paid');
        $this->assertSame('confirmed', Order::find($order['id'])->status->value);
    }

    public function test_razorpay_without_upi_s2s_offers_the_razorpay_window_instead(): void
    {
        $this->useRazorpay();
        Http::fake(function (HttpRequest $req) {
            return str_ends_with($req->url(), '/payments/create/upi')
                ? Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR', 'description' => 'S2S is not enabled for the merchant']], 400)
                : Http::response(['id' => 'order_F1', 'status' => 'created']);
        });
        [$order, , $p] = $this->placeUpiOrder();

        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertStatus(503)
            ->assertJsonPath('fallback', 'razorpay')->assertJsonPath('message', 'UPI payments are temporarily unavailable.');
        // Nothing was lost: the order is still pending with its stock reserved.
        $this->assertSame('pending', Order::find($order['id'])->status->value);
        $this->assertSame(1, Inventory::where('product_id', $p->id)->value('reserved'));
    }
}
