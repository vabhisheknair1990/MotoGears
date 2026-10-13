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
        // Razorpay reports UPI as enabled for the account (GET /methods, cached).
        \Illuminate\Support\Facades\Cache::put('razorpay.methods.'.md5('rzp_test_KEY'), ['upi' => true, 'card' => true], 3600);
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

    // ── Razorpay UPI QR codes (real provider, HTTP faked) ─────────
    /** A PNG QR code holding $link, like the poster Razorpay serves behind image_url. */
    private static function qrPng(string $link): string
    {
        return (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\GDLibRenderer(400)))->writeString($link);
    }

    private static function upiLink(float $rupees, string $qrId): string
    {
        return 'upi://pay?ver=01&mode=22&pa=motogears.rzp@icici&pn=MOTOGEARS&tr=RZP'.$qrId.'qrv2&cu=INR&mc=5013&qrMedium=04&am='.number_format($rupees, 2, '.', '');
    }

    /**
     * Fakes Razorpay's QR Codes API. Each POST creates qr_1, qr_2…; $paid maps a QR id to the
     * Razorpay payment made on it. Returns the list of requests sent ("METHOD path").
     */
    private function fakeQrApi(array &$paid = [], array &$sent = [], ?callable $imageAmount = null): void
    {
        $count = 0;
        Http::fake(function (HttpRequest $req) use (&$paid, &$sent, &$count, $imageAmount) {
            $path = (string) parse_url($req->url(), PHP_URL_PATH);
            $host = (string) parse_url($req->url(), PHP_URL_HOST);
            $sent[] = $req->method().' '.($host === 'rzp.io' ? 'rzp.io' : '').$path;
            $paise = (int) round(Payment::where('method', 'upi')->latest('id')->first()->amount * 100);

            if ($host === 'rzp.io') {
                $id = substr($path, 3);

                return Http::response(self::qrPng(self::upiLink(($imageAmount ? $imageAmount($paise) : $paise) / 100, $id)), 200, ['Content-Type' => 'image/png']);
            }

            return match (true) {
                $req->method() === 'POST' && $path === '/v1/payments/qr_codes' => Http::response([
                    'id' => 'qr_'.(++$count), 'entity' => 'qr_code', 'type' => 'upi_qr', 'usage' => 'single_use', 'status' => 'active',
                    'payment_amount' => $req['payment_amount'], 'close_by' => $req['close_by'], 'image_url' => 'https://rzp.io/i/qr_'.$count,
                ]),
                (bool) preg_match('#^/v1/payments/qr_codes/(qr_\d+)/payments$#', $path, $m) => Http::response(['items' => isset($paid[$m[1]])
                    ? [['id' => $paid[$m[1]], 'status' => 'captured', 'method' => 'upi', 'vpa' => 'asha@okicici', 'amount' => $paise, 'order_id' => null]]
                    : []]),
                (bool) preg_match('#^/v1/payments/qr_codes/qr_\d+/close$#', $path) => Http::response(['status' => 'closed']),
                $path === '/v1/orders' => Http::response(['id' => 'order_R1', 'amount' => $req['amount'], 'status' => 'created']),
                str_ends_with($path, '/refund') => Http::response(['id' => 'rfnd_'.count($sent), 'status' => 'processed']),
                default => Http::response(['error' => ['description' => 'unexpected '.$path]], 500),
            };
        });
    }

    private function qrWebhook(string $qrId, string $payId, int $paise, ?int $paymentId = null): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['event' => 'qr_code.credited', 'contains' => ['payment', 'qr_code'], 'payload' => [
            'payment' => ['entity' => ['id' => $payId, 'amount' => $paise, 'status' => 'captured', 'method' => 'upi', 'vpa' => 'x@ybl', 'order_id' => null]],
            'qr_code' => ['entity' => ['id' => $qrId, 'status' => 'closed', 'notes' => $paymentId ? ['payment_id' => $paymentId] : []]],
        ]]);

        return $this->call('POST', $this->api('webhooks/razorpay'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'whsec')], $body);
    }

    public function test_razorpay_qr_code_is_shown_and_confirmed_by_polling(): void
    {
        $this->useRazorpay();
        $paid = [];
        $sent = [];
        $this->fakeQrApi($paid, $sent);

        [$order, $res] = $this->placeUpiOrder();
        $res->assertJsonPath('data.upi.demo', false)->assertJsonPath('data.upi.collect', false);
        $payment = Payment::latest('id')->first();
        $this->assertSame('razorpay', $payment->gateway);
        $this->assertSame([], array_filter($sent, fn ($r) => str_contains($r, '/v1/orders')), 'no Razorpay order is needed for a QR code');

        $qr = $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.flow', 'intent')
            ->assertJsonPath('data.demo', false)->assertJsonPath('data.collect', false)
            // QR-code links aren't opened as app intents; phones get Razorpay's UPI app buttons.
            ->assertJsonPath('data.link', null);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr->json('data.qr'));
        $paise = (int) round($payment->amount * 100);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/qr_codes') && $r['type'] === 'upi_qr'
            && $r['usage'] === 'single_use' && $r['fixed_amount'] === true && $r['payment_amount'] === $paise
            && $r['close_by'] >= now()->addMinutes(9)->getTimestamp() && $r['notes']['payment_id'] === (string) $payment->id);
        $this->assertSame(self::upiLink($paise / 100, 'qr_1'), $payment->fresh()->meta['upi_link']);
        $this->assertSame(['qr_1'], $payment->fresh()->meta['upi_refs']);

        // Same code while it is valid; not paid yet.
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk();
        $this->assertCount(1, array_filter($sent, fn ($r) => $r === 'POST /v1/payments/qr_codes'));
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'pending');

        $paid['qr_1'] = 'pay_Q1';
        $this->travel(3)->seconds();
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.transaction_id', 'pay_Q1');
        $this->assertSame('confirmed', Order::find($order['id'])->status->value);
        $this->assertSame('asha@okicici', $payment->fresh()->meta['vpa']);
        $this->assertSame('qr_1', $payment->fresh()->meta['qr_code_id']);

        // Cancelling the paid order refunds through Razorpay.
        $this->postJson($this->api("orders/{$order['id']}/cancel"))->assertOk()->assertJsonPath('data.payment_status', 'refunded');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/pay_Q1/refund'));
    }

    public function test_a_new_qr_code_closes_the_old_one_but_a_late_payment_on_it_still_counts(): void
    {
        $this->useRazorpay();
        $paid = [];
        $sent = [];
        $this->fakeQrApi($paid, $sent);
        [$order] = $this->placeUpiOrder();

        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk();
        $this->travel(11)->minutes();
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'expired');
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk();
        $this->assertContains('POST /v1/payments/qr_codes/qr_1/close', $sent);
        $this->assertSame(['qr_1', 'qr_2'], Payment::latest('id')->first()->meta['upi_refs']);

        // The customer had already scanned the first code.
        $paid['qr_1'] = 'pay_OLD';
        $this->travel(3)->seconds();
        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'paid')->assertJsonPath('data.transaction_id', 'pay_OLD');
    }

    public function test_a_qr_code_for_the_wrong_amount_is_never_shown(): void
    {
        $this->useRazorpay();
        $paid = [];
        $sent = [];
        $this->fakeQrApi($paid, $sent, fn (int $paise) => $paise + 100);
        [$order] = $this->placeUpiOrder();

        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertStatus(503)->assertJsonPath('fallback', 'razorpay');
        $this->assertContains('POST /v1/payments/qr_codes/qr_1/close', $sent);
    }

    public function test_razorpay_webhook_confirms_a_qr_payment_when_the_page_is_closed(): void
    {
        $this->useRazorpay();
        $paid = [];
        $this->fakeQrApi($paid);
        [$order] = $this->placeUpiOrder();
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk();
        $payment = Payment::latest('id')->first();
        $paise = (int) round($payment->amount * 100);

        $this->qrWebhook('qr_9', 'pay_X', $paise, $payment->id)->assertOk()->assertJsonPath('data.result', 'unknown_order');
        $this->qrWebhook('qr_1', 'pay_X', $paise - 1, $payment->id)->assertOk()->assertJsonPath('data.result', 'amount_mismatch');
        // Notes missing: found by the QR code id we recorded.
        $this->qrWebhook('qr_1', 'pay_W1', $paise)->assertOk()->assertJsonPath('data.result', 'paid');

        $this->getJson($this->api("orders/{$order['id']}/upi/status"))->assertJsonPath('data.status', 'paid');
        $this->assertSame('confirmed', Order::find($order['id'])->status->value);
        $this->assertSame('pay_W1', $payment->fresh()->transaction_id);
    }

    public function test_opening_the_order_picks_up_a_qr_payment_without_the_webhook(): void
    {
        $this->useRazorpay();
        $paid = [];
        $this->fakeQrApi($paid);
        [$order] = $this->placeUpiOrder();
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk();

        $paid['qr_1'] = 'pay_R1';
        $this->getJson($this->api("orders/{$order['id']}"))->assertOk();
        $this->assertSame('confirmed', Order::find($order['id'])->status->value);
    }

    public function test_paying_two_qr_codes_refunds_the_second_payment(): void
    {
        $this->useRazorpay();
        $paid = [];
        $sent = [];
        $this->fakeQrApi($paid, $sent);
        [$order] = $this->placeUpiOrder();
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk();
        $this->travel(11)->minutes();
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk();
        $payment = Payment::latest('id')->first();
        $paise = (int) round($payment->amount * 100);

        $this->qrWebhook('qr_2', 'pay_A', $paise, $payment->id)->assertJsonPath('data.result', 'paid');
        $this->qrWebhook('qr_1', 'pay_B', $paise, $payment->id)->assertJsonPath('data.result', 'paid');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/pay_B/refund'));
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/pay_A/refund'));
        $this->assertSame('paid', Order::find($order['id'])->payment_status->value);
        $this->assertSame('refunded', Payment::where('transaction_id', 'pay_B')->first()->status->value);
    }

    public function test_without_upi_qr_codes_razorpay_window_takes_over(): void
    {
        $this->useRazorpay();
        Http::fake(['*/payments/qr_codes' => Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR', 'description' => 'The requested URL was not found on the server.']], 400)]);
        [$order, , $p] = $this->placeUpiOrder();

        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertStatus(503)
            ->assertJsonPath('fallback', 'razorpay')->assertJsonPath('message', 'UPI QR codes are temporarily unavailable.');
        // Nothing was lost: the order is still pending with its stock reserved.
        $this->assertSame('pending', Order::find($order['id'])->status->value);
        $this->assertSame(1, Inventory::where('product_id', $p->id)->value('reserved'));
        // …and new checkouts stop offering the in-page UPI option for a while (Razorpay still takes UPI).
        $this->assertNotContains('upi', collect($this->getJson($this->api('settings'))->json('data.payment_methods'))->pluck('code'));
    }

    public function test_upi_id_payments_go_to_the_razorpay_window(): void
    {
        $this->useRazorpay();
        $paid = [];
        $sent = [];
        $this->fakeQrApi($paid, $sent);
        [$order] = $this->placeUpiOrder();
        $this->postJson($this->api("orders/{$order['id']}/upi/qr"))->assertOk();
        $this->postJson($this->api("orders/{$order['id']}/upi/collect"), ['vpa' => 'asha@okaxis'])
            ->assertStatus(422)->assertJsonPath('fallback', 'razorpay');
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'vpa') || str_contains($r->url(), 'create/upi'));

        // Switching to the Razorpay window closes the QR code shown on our page.
        $this->postJson($this->api("orders/{$order['id']}/pay"), ['payment_method' => 'razorpay'])->assertOk()
            ->assertJsonPath('data.razorpay.order_id', 'order_R1');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/qr_codes/qr_1/close'));
    }

    public function test_upi_is_hidden_until_razorpay_enables_it(): void
    {
        $this->useRazorpay();
        \Illuminate\Support\Facades\Cache::flush();
        $methods = ['card' => true, 'upi' => false, 'netbanking' => ['HDFC' => 'HDFC Bank'], 'wallet' => ['mobikwik' => true]];
        Http::fake(['*/methods*' => function () use (&$methods) { return Http::response($methods); }]);

        $options = collect($this->getJson($this->api('settings'))->json('data.payment_methods'));
        $this->assertNotContains('upi', $options->pluck('code'));
        $this->assertSame('Credit/debit cards, net banking and wallets — secure checkout by Razorpay.', $options->firstWhere('code', 'razorpay')['description']);

        \Illuminate\Support\Facades\Cache::flush();
        $methods['upi'] = true;
        $options = collect($this->getJson($this->api('settings'))->json('data.payment_methods'));
        $this->assertSame('upi', $options->pluck('code')->first());
        $this->assertSame('UPI, credit/debit cards, net banking and wallets — secure checkout by Razorpay.', $options->firstWhere('code', 'razorpay')['description']);
    }
}
