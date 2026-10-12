<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Page;
use App\Models\Setting;
use App\Support\GstStates;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BusinessDetailsTest extends TestCase
{
    private function orderTo(string $state): array
    {
        Notification::fake();
        $user = $this->actingAsCustomer();
        $p = $this->product(['price' => 1000, 'mrp' => 1000]);
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create(['state' => $state, 'city' => 'X']);

        return $this->postJson($this->api('orders'), ['shipping_address_id' => $address->id, 'shipping_method' => 'standard', 'payment_method' => 'cod'])
            ->assertCreated()->json('data.order');
    }

    public function test_gst_certificate_details_are_public_and_on_the_invoice(): void
    {
        $this->getJson($this->api('settings'))->assertOk()
            ->assertJsonPath('data.gstin', '32AWCPT0591R2ZY')
            ->assertJsonPath('data.trade_name', 'M/S MOTO GEARS')
            ->assertJsonPath('data.legal_name', 'Ashwin Joe Thomas')
            ->assertJsonPath('data.support_phone', '+91 77366 88668')
            ->assertJsonPath('data.support_email', 'motogears46@gmail.com')
            ->assertJsonPath('data.store_address', 'Oolanpara Road, Ground Floor, TC 31/3483/3, Ambalamukku, Perurnagar, Thiruvananthapuram, Kerala 695005');

        $order = $this->orderTo('Kerala');
        $this->getJson($this->api("orders/{$order['id']}/invoice"))->assertOk()
            ->assertJsonPath('data.seller.gstin', '32AWCPT0591R2ZY')
            ->assertJsonPath('data.seller.trade_name', 'M/S MOTO GEARS')
            ->assertJsonPath('data.seller.legal_name', 'Ashwin Joe Thomas')
            ->assertJsonPath('data.seller.state', 'Kerala')
            ->assertJsonPath('data.seller.state_code', '32')
            ->assertJsonPath('data.place_of_supply', 'Kerala (32)')
            ->assertJsonPath('data.tax_split', 'CGST+SGST');

        $html = $this->get($this->api("orders/{$order['id']}/invoice/download"))->assertOk()->getContent();
        $this->assertStringContainsString('M/S MOTO GEARS', $html);
        $this->assertStringContainsString('Proprietor: Ashwin Joe Thomas', $html);
        $this->assertStringContainsString('32AWCPT0591R2ZY', $html);
    }

    public function test_supply_to_another_state_is_igst(): void
    {
        $order = $this->orderTo('Karnataka');
        $this->getJson($this->api("orders/{$order['id']}/invoice"))
            ->assertJsonPath('data.tax_split', 'IGST')->assertJsonPath('data.place_of_supply', 'Karnataka (29)');
    }

    public function test_gst_state_codes(): void
    {
        $this->assertSame('Kerala', GstStates::fromGstin('32AWCPT0591R2ZY'));
        $this->assertSame('Tamil Nadu', GstStates::fromGstin('33ABCDE1234F1Z5'));
        $this->assertNull(GstStates::fromGstin('not-a-gstin'));
        $this->assertTrue(GstStates::same('Jammu & Kashmir', 'Jammu and Kashmir'));
        $this->assertSame('32', GstStates::codeOf('kerala'));
    }

    public function test_migration_updates_an_existing_store(): void
    {
        Setting::where('key', 'gstin')->update(['value' => '29ABCDE1234F1Z5']);
        Setting::whereIn('key', ['trade_name', 'legal_name'])->delete();
        Page::create(['title' => 'About', 'slug' => 'about', 'content' => '<p>MotoGears was started in Bengaluru in 2019 by riders.</p>']);
        Page::create(['title' => 'Shipping', 'slug' => 'shipping-policy', 'content' => '<p>We ship across India from our Bengaluru and Gurugram warehouses.</p>']);

        (require database_path('migrations/2026_10_12_000100_update_business_details_from_gst_certificate.php'))->up();

        Setting::where('key', 'support_phone')->update(['value' => '+91 80 4719 2200']);
        (require database_path('migrations/2026_10_12_000200_update_support_contact.php'))->up();
        $this->assertSame('+91 77366 88668', Setting::where('key', 'support_phone')->value('value'));
        $this->assertSame('motogears46@gmail.com', Setting::where('key', 'support_email')->value('value'));

        $this->assertSame('32AWCPT0591R2ZY', Setting::where('key', 'gstin')->value('value'));
        $this->assertTrue((bool) Setting::where('key', 'trade_name')->value('is_public'));
        $this->assertSame('Ashwin Joe Thomas', Setting::where('key', 'legal_name')->value('value'));
        $this->assertStringContainsString('started in Thiruvananthapuram in 2019', Page::where('slug', 'about')->value('content'));
        $this->assertStringContainsString('from Thiruvananthapuram, Kerala.', Page::where('slug', 'shipping-policy')->value('content'));
    }

    public function test_admin_rejects_a_malformed_gstin(): void
    {
        $this->actingAsStaff();
        $this->putJson($this->api('admin/settings'), ['settings' => ['gstin' => '12345']])->assertUnprocessable();
        $this->putJson($this->api('admin/settings'), ['settings' => ['gstin' => '32AWCPT0591R2ZY', 'trade_name' => 'M/S MOTO GEARS']])->assertOk();
    }
}
