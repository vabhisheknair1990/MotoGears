<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    public function test_only_purchasers_can_review_and_reviews_need_approval(): void
    {
        Storage::fake('public');
        Notification::fake();
        $p = $this->product();

        $this->actingAsCustomer();
        $this->postJson($this->api("products/{$p->id}/reviews"), ['rating' => 5, 'comment' => 'I never bought this but love it'])->assertForbidden();

        $buyer = $this->actingAsCustomer();
        $this->postJson($this->api('cart/items'), ['product_id' => $p->id]);
        $this->postJson($this->api('orders'), ['shipping_address_id' => $this->address($buyer)->id, 'shipping_method' => 'standard', 'payment_method' => 'cod'])->assertCreated();

        $review = $this->post($this->api("products/{$p->id}/reviews"), [
            'rating' => 4, 'title' => 'Solid', 'comment' => 'Fits perfectly and brakes well.', 'images' => [UploadedFile::fake()->image('pad.jpg')],
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.is_verified_purchase', true)->json('data');
        $this->assertCount(1, $review['images']);

        $this->postJson($this->api("products/{$p->id}/reviews"), ['rating' => 5, 'comment' => 'Second review attempt here'])->assertStatus(422);
        $this->getJson($this->api("products/{$p->id}/reviews"))->assertJsonPath('meta.total', 0); // pending hidden

        $this->actingAsStaff(Role::CONTENT_MANAGER);
        $this->patchJson($this->api("admin/reviews/{$review['id']}"), ['status' => 'approved', 'is_featured' => true])->assertOk();

        $this->getJson($this->api("products/{$p->id}/reviews"))->assertJsonPath('meta.total', 1)->assertJsonPath('meta.summary.average', 4);
        $this->assertEquals(4, $p->fresh()->rating_avg);
        $this->assertSame(1, $p->fresh()->rating_count);
    }
}
