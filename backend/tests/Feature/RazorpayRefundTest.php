<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RazorpayRefundTest extends TestCase
{
    private bool $balanceLow = true;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['payments.razorpay.key_id' => 'rzp_test_KEY', 'payments.razorpay.key_secret' => 'sek', 'payments.razorpay.base_url' => 'https://api.razorpay.test/v1']);
        Cache::put('razorpay.methods.'.md5('rzp_test_KEY'), ['card' => true, 'upi' => false], 3600);
        Http::fake(function (HttpRequest $req) {
            $path = parse_url($req->url(), PHP_URL_PATH);
            $paise = (int) round((float) Payment::latest('id')->value('amount') * 100);

            return match (true) {
                $path === '/v1/orders' => Http::response(['id' => 'order_R1', 'status' => 'created']),
                $path === '/v1/orders/order_R1/payments' => Http::response(['items' => [['id' => 'pay_R1', 'status' => 'captured', 'method' => 'card', 'amount' => $paise, 'order_id' => 'order_R1', 'card' => ['network' => 'MasterCard', 'last4' => '5449']]]]),
                str_ends_with($path, '/refund') => $this->balanceLow
                    ? Http::response(['error' => ['code' => 'BAD_REQUEST_ERROR', 'description' => 'invalid request sent']], 400)
                    : Http::response(['id' => 'rfnd_R1', 'status' => 'processed']),
                default => Http::response(['error' => ['description' => 'unexpected '.$path]], 500),
            };
        });
    }

    public function test_paid_order_is_found_when_the_shopper_closed_the_window_and_refund_can_be_retried(): void
    {
        $user = $this->actingAsCustomer();
        $p = $this->product(['price' => 1000, 'mrp' => 1000]);
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => 1]);
        $order = $this->postJson($this->api('orders'), ['shipping_address_id' => $this->address($user)->id, 'shipping_method' => 'standard', 'payment_method' => 'razorpay'])
            ->assertCreated()->json('data.order');
        $this->assertSame('pending', $order['payment_status']);

        // Paid in Razorpay, but the browser never came back: opening the order asks Razorpay.
        $this->getJson($this->api("orders/{$order['id']}"))->assertOk()
            ->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.payment_status', 'paid');
        $this->assertSame('reconcile', Payment::latest('id')->first()->meta['confirmed_via']);

        // Cancelling refunds; a low Razorpay balance makes it fail — staff see why, the customer doesn't.
        $this->postJson($this->api("orders/{$order['id']}/cancel"))->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $customerTimeline = collect($this->getJson($this->api("orders/{$order['id']}"))->json('data.timeline'))->pluck('comment')->implode(' | ');
        $this->assertStringContainsString('is being processed', $customerTimeline);
        $this->assertStringNotContainsString('Razorpay refused', $customerTimeline);

        $this->actingAsStaff(Role::ADMIN);
        $admin = $this->getJson($this->api("admin/orders/{$order['id']}"))->assertJsonPath('data.refund_pending', true);
        $this->assertStringContainsString('Razorpay account balance is lower than the refund amount', collect($admin->json('data.timeline'))->pluck('comment')->implode(' '));

        $this->postJson($this->api("admin/orders/{$order['id']}/refund"))->assertUnprocessable();
        $this->balanceLow = false;
        $this->postJson($this->api("admin/orders/{$order['id']}/refund"))->assertOk()
            ->assertJsonPath('data.payment_status', 'refunded')->assertJsonPath('data.refund_pending', false);
        $this->postJson($this->api("admin/orders/{$order['id']}/refund"))->assertUnprocessable();
        $this->assertSame('refunded', Order::find($order['id'])->payment_status->value);
    }
}
