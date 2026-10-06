<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketingAdminTest extends TestCase
{
    public function test_coupon_crud_and_storefront_application(): void
    {
        $this->actingAsStaff(Role::CONTENT_MANAGER);
        $coupon = $this->postJson($this->api('admin/coupons'), [
            'code' => 'festive20', 'name' => 'Festive', 'type' => 'percentage', 'value' => 20, 'max_discount' => 400,
            'starts_at' => now()->subDay()->toIso8601String(), 'expires_at' => now()->addWeek()->toIso8601String(), 'usage_limit' => 100,
        ])->assertCreated()->assertJsonPath('data.code', 'FESTIVE20')->json('data');
        $this->postJson($this->api('admin/coupons'), ['code' => 'BAD', 'name' => 'x', 'type' => 'percentage', 'value' => 150])->assertStatus(422);
        $this->putJson($this->api("admin/coupons/{$coupon['id']}"), ['max_discount' => 250])->assertOk();

        $this->actingAsCustomer();
        $p = $this->product(['price' => 2000, 'mrp' => 2000]);
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id]);
        $this->postJson($this->api('cart/apply-coupon'), ['code' => 'FESTIVE20'])->assertOk()->assertJsonPath('data.discount', 250);
    }

    public function test_banner_crud_and_public_listing(): void
    {
        Storage::fake('public');
        $this->actingAsStaff(Role::CONTENT_MANAGER);

        $banner = $this->post($this->api('admin/banners'), [
            'title' => 'Monsoon Sale', 'subtitle' => 'Wipers from ₹499', 'cta_label' => 'Shop', 'cta_url' => '/category/wipers',
            'placement' => 'hero', 'desktop_image' => UploadedFile::fake()->image('hero.jpg', 1600, 600),
        ])->assertCreated()->json('data');
        $this->post($this->api('admin/banners'), ['title' => 'No image'])->assertStatus(422);
        $this->post($this->api('admin/banners'), ['title' => 'Bad link', 'cta_url' => 'javascript:alert(1)', 'desktop_image' => UploadedFile::fake()->image('x.jpg')])->assertStatus(422);

        $this->getJson($this->api('banners?placement=hero'))->assertOk()->assertJsonPath('data.0.title', 'Monsoon Sale');
        $this->putJson($this->api("admin/banners/{$banner['id']}"), ['is_active' => false])->assertOk();
        $this->getJson($this->api('banners'))->assertJsonCount(0, 'data');
        $this->getJson($this->api('homepage'))->assertJsonCount(0, 'data.hero_banners');
    }

    public function test_cms_pages_faqs_and_blog(): void
    {
        $this->actingAsStaff(Role::CONTENT_MANAGER);
        $this->postJson($this->api('admin/pages'), ['title' => 'About Us', 'content' => '<p>Hello</p>'])->assertCreated()->assertJsonPath('data.slug', 'about-us');
        $this->postJson($this->api('admin/faqs'), ['category' => 'Orders', 'question' => 'Q?', 'answer' => 'A.'])->assertCreated();
        $post = $this->postJson($this->api('admin/blog'), ['title' => 'Brake Care 101', 'content' => '<p>'.str_repeat('word ', 450).'</p>', 'status' => 'published', 'tags' => ['brakes', 'safety']])
            ->assertCreated()->assertJsonPath('data.reading_minutes', 3)->json('data');

        $this->getJson($this->api('pages/about-us'))->assertOk();
        $this->getJson($this->api('faqs'))->assertJsonPath('data.0.category', 'Orders');
        $this->getJson($this->api('blog'))->assertJsonPath('meta.total', 1);
        $this->getJson($this->api('blog/brake-care-101'))->assertOk()->assertJsonCount(2, 'data.tags');

        $this->putJson($this->api("admin/blog/{$post['id']}"), ['status' => 'draft'])->assertOk();
        $this->getJson($this->api('blog/brake-care-101'))->assertNotFound();
    }

    public function test_contact_form_and_newsletter_are_stored(): void
    {
        $this->postJson($this->api('contact'), ['name' => 'Ravi', 'email' => 'ravi@example.com', 'subject' => 'Fitment', 'message' => 'Does this fit my Nexon EV?'])->assertCreated();
        $this->postJson($this->api('contact'), ['name' => 'Bot', 'email' => 'b@example.com', 'subject' => 'x', 'message' => 'spam spam spam', 'website' => 'http://spam'])->assertStatus(422);
        $this->postJson($this->api('newsletter'), ['email' => 'News@Example.com'])->assertCreated();
        $this->assertDatabaseHas('contact_messages', ['email' => 'ravi@example.com', 'status' => 'new']);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'news@example.com']);

        $this->actingAsStaff(Role::CONTENT_MANAGER);
        $id = $this->getJson($this->api('admin/contact-messages'))->assertJsonPath('meta.total', 1)->json('data.0.id');
        $this->getJson($this->api("admin/contact-messages/{$id}"))->assertOk();
        $this->assertDatabaseHas('contact_messages', ['id' => $id, 'status' => 'read']);
    }

    public function test_settings_and_staff_management(): void
    {
        $admin = $this->actingAsStaff(Role::SUPER_ADMIN);
        $this->putJson($this->api('admin/settings'), ['settings' => ['free_shipping_threshold' => 1999, 'cod_enabled' => false]])->assertOk();
        $this->getJson($this->api('settings'))->assertJsonPath('data.free_shipping_threshold', 1999);

        $staff = $this->postJson($this->api('admin/staff'), ['name' => 'New Packer', 'email' => 'packer@example.com', 'password' => 'secret123', 'roles' => ['order_manager']])
            ->assertCreated()->json('data');
        $this->postJson($this->api('admin/staff'), ['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123', 'roles' => ['customer']])->assertStatus(422);
        $this->deleteJson($this->api("admin/staff/{$admin->id}"))->assertStatus(422);
        $this->deleteJson($this->api("admin/staff/{$staff['id']}"))->assertOk();

        $this->assertTrue(User::withTrashed()->find($staff['id'])->trashed());
    }

    public function test_customer_management(): void
    {
        $c = $this->customer();
        $this->actingAsStaff(Role::ADMIN);
        $this->getJson($this->api('admin/customers'))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.orders_count', 0);
        $this->getJson($this->api("admin/customers/{$c->id}"))->assertOk()->assertJsonStructure(['data' => ['orders', 'reviews', 'addresses', 'vehicles', 'total_spent']]);
        $this->patchJson($this->api("admin/customers/{$c->id}"), ['is_active' => false])->assertOk();
        $this->assertFalse($c->fresh()->is_active);
    }
}
