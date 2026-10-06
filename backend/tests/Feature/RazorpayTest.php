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

class RazorpayTest extends TestCase
{
    private const SECRET = 'test_secret_abc123';

    private const WEBHOOK_SECRET = 'whsec_test_456';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config([
            'payments.razorpay.key_id' => 'rzp_test_KEY123',
            'payments.razorpay.key_secret' => self::SECRET,
            'payments.razorpay.webhook_secret' => self::WEBHOOK_SECRET,
            'payments.razorpay.base_url' => 'https://api.razorpay.test/v1',
        ]);
        $this->user = $this->actingAsCustomer();
    }

    /** Fake Razorpay: orders get sequential ids; payments are "captured" for the amount asked. */
    private function fakeRazorpay(string $paymentStatus = 'captured', ?int $amountOverride = null): void
    {
        $counter = 0;
        Http::fake(function (HttpRequest $req) use (&$counter, $paymentStatus, $amountOverride) {
            $path = parse_url($req->url(), PHP_URL_PATH);
            if ($req->method() === 'POST' && str_ends_with($path, '/v1/orders')) {
                $counter++;

                return Http::response(['id' => 'order_TEST'.$counter, 'entity' => 'order', 'amount' => $req['amount'], 'currency' => 'INR', 'status' => 'created'], 200);
            }
            if ($req->method() === 'GET' && preg_match('#/payments/(pay_\w+)$#', $path, $m)) {
                $order = Payment::where('transaction_id', null)->latest('id')->first();

                return Http::response([
                    'id' => $m[1], 'entity' => 'payment', 'status' => $paymentStatus, 'method' => 'upi', 'vpa' => 'success@razorpay',
                    'order_id' => $order?->gateway_order_id, 'amount' => $amountOverride ?? (int) round($order->amount * 100), 'currency' => 'INR',
                    'error_description' => $paymentStatus === 'failed' ? 'Payment declined by bank' : null,
                ], 200);
            }
            if ($req->method() === 'POST' && preg_match('#/payments/(pay_\w+)/capture$#', $path, $m)) {
                return Http::response(['id' => $m[1], 'status' => 'captured', 'amount' => $req['amount'], 'order_id' => Payment::latest('id')->first()->gateway_order_id, 'method' => 'card', 'card' => ['network' => 'Visa', 'last4' => '1111']], 200);
            }
            if ($req->method() === 'POST' && preg_match('#/payments/(pay_\w+)/refund$#', $path)) {
                return Http::response(['id' => 'rfnd_TEST1', 'entity' => 'refund', 'amount' => $req['amount'], 'status' => 'processed'], 200);
            }

            return Http::response(['error' => ['description' => 'not faked']], 404);
        });
    }

    private function placeRazorpayOrder(): \Illuminate\Testing\TestResponse
    {
        $p = $this->product(['price' => 1500, 'mrp' => 2000], stock: 10);
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => 2])->assertCreated();

        return $this->postJson($this->api('orders'), [
            'shipping_address_id' => $this->address($this->user)->id,
            'shipping_method' => 'standard',
            'payment_method' => 'razorpay',
        ]);
    }

    private function sign(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, self::SECRET);
    }

    public function test_razorpay_is_offered_only_when_configured(): void
    {
        $this->product();
        $this->getJson($this->api('checkout'))->assertOk()->assertJsonFragment(['code' => 'razorpay']);

        config(['payments.razorpay.key_id' => null]);
        $codes = collect($this->getJson($this->api('checkout'))->json('data.payment_methods'))->pluck('code');
        $this->assertNotContains('razorpay', $codes);

        config(['payments.demo_methods' => false]);
        $codes = collect($this->getJson($this->api('checkout'))->json('data.payment_methods'))->pluck('code');
        $this->assertSame(['cod'], $codes->all());
    }

    public function test_placing_order_creates_razorpay_order_for_server_total_without_leaking_secret(): void
    {
        $this->fakeRazorpay();
        $res = $this->placeRazorpayOrder()->assertCreated()
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.payment_status', 'pending')
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.payment_successful', false)
            ->assertJsonPath('data.razorpay.key', 'rzp_test_KEY123')
            ->assertJsonPath('data.razorpay.order_id', 'order_TEST1')
            ->assertJsonPath('data.razorpay.amount', 354000) // ₹3,540 incl. GST, in paise
            ->assertJsonPath('data.razorpay.currency', 'INR');

        $this->assertStringNotContainsString(self::SECRET, $res->getContent());
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/orders') && $r['amount'] === 354000
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_KEY123:'.self::SECRET)));
        // Stock is held while the customer pays.
        $this->assertSame(2, Inventory::first()->reserved);
    }

    public function test_valid_signature_confirms_order(): void
    {
        $this->fakeRazorpay();
        $number = $this->placeRazorpayOrder()->json('data.order.order_number');

        $this->postJson($this->api("orders/{$number}/razorpay/verify"), [
            'razorpay_order_id' => 'order_TEST1',
            'razorpay_payment_id' => 'pay_ABC123',
            'razorpay_signature' => $this->sign('order_TEST1', 'pay_ABC123'),
        ])->assertOk()
            ->assertJsonPath('data.payment_successful', true)
            ->assertJsonPath('data.order.status', 'confirmed')
            ->assertJsonPath('data.order.payment_status', 'paid')
            ->assertJsonPath('data.payment.transaction_id', 'pay_ABC123')
            ->assertJsonPath('data.payment.details.vpa', 'success@razorpay');

        // Replaying the same callback is harmless.
        $this->postJson($this->api("orders/{$number}/razorpay/verify"), [
            'razorpay_order_id' => 'order_TEST1',
            'razorpay_payment_id' => 'pay_ABC123',
            'razorpay_signature' => $this->sign('order_TEST1', 'pay_ABC123'),
        ])->assertOk();
        $this->assertSame(1, Order::first()->statusHistory()->where('status', 'confirmed')->count());
    }

    public function test_authorized_payment_is_captured(): void
    {
        $this->fakeRazorpay('authorized');
        $number = $this->placeRazorpayOrder()->json('data.order.order_number');

        $this->postJson($this->api("orders/{$number}/razorpay/verify"), [
            'razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_AUTH1', 'razorpay_signature' => $this->sign('order_TEST1', 'pay_AUTH1'),
        ])->assertOk()->assertJsonPath('data.order.payment_status', 'paid')->assertJsonPath('data.payment.details.last4', '1111');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/pay_AUTH1/capture') && $r['amount'] === 354000);
    }

    public function test_tampered_signature_or_amount_is_rejected(): void
    {
        $this->fakeRazorpay('captured', amountOverride: 100); // Razorpay reports ₹1 paid for a ₹3,540 order
        $number = $this->placeRazorpayOrder()->json('data.order.order_number');

        $this->postJson($this->api("orders/{$number}/razorpay/verify"), [
            'razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => str_repeat('a', 64),
        ])->assertStatus(422);
        $this->assertSame('pending', Order::first()->payment_status->value);

        $this->postJson($this->api("orders/{$number}/razorpay/verify"), [
            'razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_Y', 'razorpay_signature' => $this->sign('order_TEST1', 'pay_Y'),
        ])->assertStatus(422);
        $this->assertSame('pending', Order::first()->status->value);
    }

    public function test_other_customers_cannot_confirm_payment(): void
    {
        $this->fakeRazorpay();
        $number = $this->placeRazorpayOrder()->json('data.order.order_number');
        $this->actingAsCustomer();

        $this->postJson($this->api("orders/{$number}/razorpay/verify"), [
            'razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_Z', 'razorpay_signature' => $this->sign('order_TEST1', 'pay_Z'),
        ])->assertForbidden();
    }

    public function test_failure_then_retry_creates_new_razorpay_order(): void
    {
        $this->fakeRazorpay();
        $number = $this->placeRazorpayOrder()->json('data.order.order_number');

        $this->postJson($this->api("orders/{$number}/razorpay/failed"), ['razorpay_order_id' => 'order_TEST1', 'reason' => 'Card declined'])->assertOk();
        $this->getJson($this->api("orders/{$number}"))->assertJsonPath('data.payment_status', 'failed')->assertJsonPath('data.can_pay', true);

        $this->postJson($this->api("orders/{$number}/pay"), ['payment_method' => 'razorpay', 'payment_details' => null])->assertOk()
            ->assertJsonPath('data.razorpay.order_id', 'order_TEST2');

        $this->postJson($this->api("orders/{$number}/razorpay/verify"), [
            'razorpay_order_id' => 'order_TEST2', 'razorpay_payment_id' => 'pay_RETRY', 'razorpay_signature' => $this->sign('order_TEST2', 'pay_RETRY'),
        ])->assertOk()->assertJsonPath('data.order.payment_status', 'paid');
    }

    public function test_webhook_requires_valid_signature_and_confirms_payment(): void
    {
        $this->fakeRazorpay();
        $this->placeRazorpayOrder();
        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_HOOK1', 'order_id' => 'order_TEST1', 'amount' => 354000, 'status' => 'captured', 'method' => 'netbanking', 'bank' => 'HDFC',
        ]]]]);

        $this->call('POST', $this->api('webhooks/razorpay'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => 'bad'], $body)
            ->assertStatus(400);
        $this->assertSame('pending', Order::first()->payment_status->value);

        $sig = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);
        $this->call('POST', $this->api('webhooks/razorpay'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $sig], $body)
            ->assertOk()->assertJsonPath('data.result', 'paid');

        $order = Order::first();
        $this->assertSame('confirmed', $order->status->value);
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('pay_HOOK1', $order->payments()->first()->transaction_id);
    }

    public function test_cancelling_paid_order_refunds_through_razorpay(): void
    {
        $this->fakeRazorpay();
        $number = $this->placeRazorpayOrder()->json('data.order.order_number');
        $this->postJson($this->api("orders/{$number}/razorpay/verify"), [
            'razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_R1', 'razorpay_signature' => $this->sign('order_TEST1', 'pay_R1'),
        ])->assertOk();

        $this->postJson($this->api("orders/{$number}/cancel"), ['reason' => 'Ordered by mistake'])->assertOk();

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payments/pay_R1/refund') && $r['amount'] === 354000);
        $order = Order::first();
        $this->assertSame('refunded', $order->payment_status->value);
        $this->assertSame(0, Inventory::first()->reserved);
    }

    public function test_razorpay_outage_fails_gracefully(): void
    {
        Http::fake(['*' => Http::response(['error' => ['description' => 'Server error']], 500)]);

        $this->placeRazorpayOrder()->assertCreated()
            ->assertJsonPath('data.payment.status', 'failed')
            ->assertJsonPath('data.razorpay', null)
            ->assertJsonPath('data.order.can_pay', true);
    }
}
