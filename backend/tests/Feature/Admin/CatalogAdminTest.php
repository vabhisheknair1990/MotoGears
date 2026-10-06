<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\VehicleVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAsStaff(Role::CATALOG_MANAGER);
    }

    public function test_create_update_and_delete_product_with_compatibility(): void
    {
        $cat = Category::factory()->create();
        $brand = Brand::factory()->create();
        $variant = VehicleVariant::factory()->create();

        $res = $this->postJson($this->api('admin/products'), [
            'name' => 'Hella LED Fog Lamp', 'sku' => 'hl-fog-01', 'part_number' => '1NA-001', 'category_id' => $cat->id, 'brand_id' => $brand->id,
            'mrp' => 6999, 'price' => 5499, 'cost_price' => 3500, 'tax_rate' => 18, 'vehicle_type' => 'car',
            'specifications' => [['label' => 'Power', 'value' => '12W']], 'whats_included' => ['2 lamps'], 'tags' => ['fog'],
            'initial_stock' => 25, 'low_stock_threshold' => 4,
            'compatibilities' => [['vehicle_manufacturer_id' => $variant->model->vehicle_manufacturer_id, 'vehicle_variant_id' => $variant->id, 'year_from' => 2021]],
            'faqs' => [['question' => 'Waterproof?', 'answer' => 'Yes, IP67.']],
        ])->assertCreated()
            ->assertJsonPath('data.sku', 'HL-FOG-01')
            ->assertJsonPath('data.slug', 'hella-led-fog-lamp')
            ->assertJsonPath('data.inventory.quantity', 25)
            ->assertJsonPath('data.compatibility.0.variant.id', $variant->id)
            ->assertJsonPath('data.compatibility.0.model.id', $variant->vehicle_model_id); // parent IDs derived server-side
        $id = $res->json('data.id');

        // Storefront sees it immediately.
        $this->getJson($this->api('products/hella-led-fog-lamp'))->assertOk()->assertJsonPath('data.price', 5499);
        $this->getJson($this->api("products?vehicle_variant={$variant->id}"))->assertJsonPath('meta.total', 1);

        $this->putJson($this->api("admin/products/{$id}"), ['price' => 4999, 'is_featured' => true])->assertOk()->assertJsonPath('data.price', 4999);
        $this->putJson($this->api("admin/products/{$id}"), ['price' => 9999])->assertStatus(422)->assertJsonValidationErrors('price'); // > MRP
        $this->putJson($this->api("admin/products/{$id}/compatibilities"), ['compatibilities' => []])->assertOk()->assertJsonCount(0, 'data');

        $this->deleteJson($this->api("admin/products/{$id}"))->assertOk();
        $this->getJson($this->api('products/hella-led-fog-lamp'))->assertNotFound();
        $this->getJson($this->api('admin/products?status=trashed'))->assertJsonPath('meta.total', 1);
        $this->postJson($this->api("admin/products/{$id}/restore"))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.created', 'auditable_id' => $id]);
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $this->product(['sku' => 'DUP-1']);
        $this->postJson($this->api('admin/products'), [
            'name' => 'X', 'sku' => 'DUP-1', 'category_id' => Category::factory()->create()->id, 'brand_id' => Brand::factory()->create()->id,
            'mrp' => 10, 'price' => 10, 'vehicle_type' => 'car',
        ])->assertStatus(422)->assertJsonValidationErrors('sku');
    }

    public function test_upload_reorder_and_delete_images(): void
    {
        $p = $this->product();

        $images = $this->post($this->api("admin/products/{$p->id}/images"), [
            'images' => [UploadedFile::fake()->image('a.jpg', 800, 800), UploadedFile::fake()->image('b.png', 800, 800)],
        ])->assertCreated()->assertJsonCount(2, 'data')->json('data');

        $this->assertTrue($images[0]['is_primary']);
        Storage::disk('public')->assertExists(Product::find($p->id)->images()->first()->path);

        $this->post($this->api("admin/products/{$p->id}/images"), ['images' => [UploadedFile::fake()->create('evil.php', 10, 'application/x-php')]])
            ->assertStatus(422);

        $this->patchJson($this->api("admin/products/{$p->id}/images/{$images[1]['id']}"), ['is_primary' => true])->assertOk()->assertJsonPath('data.0.id', $images[1]['id']);
        $this->deleteJson($this->api("admin/products/{$p->id}/images/{$images[1]['id']}"))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_primary', true);
        $this->getJson($this->api("products/{$p->slug}"))->assertJsonCount(1, 'data.images');
    }

    public function test_category_crud_with_image_and_cycle_protection(): void
    {
        $root = $this->post($this->api('admin/categories'), ['name' => 'Lighting', 'image' => UploadedFile::fake()->image('c.png')])
            ->assertCreated()->assertJsonPath('data.slug', 'lighting')->json('data');
        $this->assertNotNull($root['image']);
        $child = $this->postJson($this->api('admin/categories'), ['name' => 'Fog Lamps', 'parent_id' => $root['id']])->assertCreated()->json('data');

        $this->putJson($this->api("admin/categories/{$root['id']}"), ['parent_id' => $child['id']])->assertStatus(422);
        $this->putJson($this->api("admin/categories/{$child['id']}"), ['name' => 'Fog & Driving Lamps', 'is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->deleteJson($this->api("admin/categories/{$root['id']}"))->assertStatus(422); // has children
        $this->deleteJson($this->api("admin/categories/{$child['id']}"))->assertOk();
        $this->getJson($this->api('categories'))->assertJsonPath('data.0.slug', 'lighting');
    }

    public function test_brand_crud(): void
    {
        $brand = $this->postJson($this->api('admin/brands'), ['name' => 'Brembo', 'country' => 'Italy', 'website' => 'https://www.brembo.com'])->assertCreated()->json('data');
        $this->postJson($this->api('admin/brands'), ['name' => 'Brembo'])->assertStatus(422);
        $this->putJson($this->api("admin/brands/{$brand['id']}"), ['description' => 'Brakes'])->assertOk()->assertJsonPath('data.description', 'Brakes');
        $this->getJson($this->api('brands/brembo'))->assertOk();

        $this->product(['brand_id' => $brand['id']]);
        $this->deleteJson($this->api("admin/brands/{$brand['id']}"))->assertStatus(422);
    }

    public function test_vehicle_hierarchy_management(): void
    {
        $m = $this->postJson($this->api('admin/vehicles/manufacturers'), ['name' => 'Mahindra', 'vehicle_type' => 'car'])->assertCreated()->json('data');
        $model = $this->postJson($this->api('admin/vehicles/models'), ['vehicle_manufacturer_id' => $m['id'], 'name' => 'Thar', 'vehicle_type' => 'car'])->assertCreated()->json('data');
        $this->postJson($this->api('admin/vehicles/models'), ['vehicle_manufacturer_id' => $m['id'], 'name' => 'Thar', 'vehicle_type' => 'car'])->assertStatus(422);
        $v = $this->postJson($this->api('admin/vehicles/variants'), [
            'vehicle_model_id' => $model['id'], 'name' => '2.0 Petrol AT', 'year_from' => 2020, 'fuel_type' => 'Petrol', 'transmission' => 'AT', 'displacement_cc' => 1997,
        ])->assertCreated()->assertJsonPath('data.year_range', '2020–present')->json('data');
        $this->postJson($this->api('admin/vehicles/variants'), ['vehicle_model_id' => $model['id'], 'name' => 'X', 'year_from' => 2020, 'year_to' => 2019])->assertStatus(422);

        $this->getJson($this->api("admin/vehicles/variants?model_id={$model['id']}"))->assertJsonCount(1, 'data');
        $this->getJson($this->api("vehicles/variants?model_id={$model['id']}"))->assertJsonPath('data.0.id', $v['id']);

        $this->putJson($this->api("admin/vehicles/variants/{$v['id']}"), ['year_to' => 2024])->assertOk()->assertJsonPath('data.year_to', 2024);
        $this->deleteJson($this->api("admin/vehicles/variants/{$v['id']}"))->assertOk();
        $this->deleteJson($this->api("admin/vehicles/models/{$model['id']}"))->assertOk();
        $this->deleteJson($this->api("admin/vehicles/manufacturers/{$m['id']}"))->assertOk();
    }
}
