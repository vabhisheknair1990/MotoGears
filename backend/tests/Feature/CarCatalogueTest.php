<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Testimonial;
use App\Models\VehicleManufacturer;
use App\Models\VehicleModel;
use Tests\TestCase;

/** "Sell car parts" off: the storefront shows no car parts, makes or brands; the admin still does. */
class CarCatalogueTest extends TestCase
{
    private Product $carPart;
    private Product $bikePart;
    private Product $universal;

    protected function setUp(): void
    {
        parent::setUp();
        $carCat = Category::factory()->create(['name' => 'Car Parts', 'slug' => 'car-parts', 'vehicle_type' => 'car']);
        $bikeCat = Category::factory()->create(['name' => 'Motorcycle Parts', 'slug' => 'motorcycle-parts', 'vehicle_type' => 'motorcycle']);
        $accCat = Category::factory()->create(['name' => 'Accessories', 'slug' => 'accessories', 'vehicle_type' => 'universal']);
        $carBrand = Brand::factory()->create(['name' => 'Denso', 'slug' => 'denso']);
        $bikeBrand = Brand::factory()->create(['name' => 'Rolon', 'slug' => 'rolon']);
        $this->carPart = $this->product(['name' => 'Denso Spark Plug for Maruti Swift', 'category_id' => $carCat->id, 'brand_id' => $carBrand->id, 'vehicle_type' => 'car']);
        $this->bikePart = $this->product(['name' => 'Rolon Chain Kit for Royal Enfield', 'category_id' => $bikeCat->id, 'brand_id' => $bikeBrand->id, 'vehicle_type' => 'motorcycle']);
        $this->universal = $this->product(['name' => 'Microfibre Cloth', 'category_id' => $accCat->id, 'brand_id' => $bikeBrand->id, 'vehicle_type' => 'universal', 'is_universal' => true]);
        $maruti = VehicleManufacturer::factory()->create(['name' => 'Maruti Suzuki', 'slug' => 'maruti-suzuki', 'vehicle_type' => 'car']);
        VehicleModel::factory()->create(['vehicle_manufacturer_id' => $maruti->id, 'name' => 'Swift', 'slug' => 'swift', 'vehicle_type' => 'car']);
        $re = VehicleManufacturer::factory()->create(['name' => 'Royal Enfield', 'slug' => 'royal-enfield', 'vehicle_type' => 'motorcycle']);
        VehicleModel::factory()->create(['vehicle_manufacturer_id' => $re->id, 'name' => 'Classic 350', 'slug' => 'classic-350', 'vehicle_type' => 'motorcycle']);
        Testimonial::create(['name' => 'Rahul', 'location' => 'Kochi', 'vehicle' => 'Maruti Suzuki Swift', 'rating' => 5, 'content' => 'Great plugs for my car.', 'is_active' => true, 'sort_order' => 1]);
        Testimonial::create(['name' => 'Arjun', 'location' => 'Kochi', 'vehicle' => 'Royal Enfield Classic 350', 'rating' => 5, 'content' => 'Chain kit fitted perfectly.', 'is_active' => true, 'sort_order' => 2]);
        $this->sellCarParts(false);
    }

    public function test_storefront_hides_car_parts_makes_and_car_only_brands(): void
    {
        $names = collect($this->getJson($this->api('products?per_page=50'))->assertOk()->json('data'))->pluck('name');
        $this->assertNotContains($this->carPart->name, $names);
        $this->assertContains($this->bikePart->name, $names);
        $this->assertContains($this->universal->name, $names);

        $this->getJson($this->api("products/{$this->carPart->slug}"))->assertNotFound();
        $this->getJson($this->api("products/{$this->bikePart->slug}"))->assertOk();
        $this->getJson($this->api('categories/car-parts'))->assertNotFound();
        $this->assertNotContains('car-parts', collect($this->getJson($this->api('categories'))->json('data'))->pluck('slug'));

        $brands = collect($this->getJson($this->api('brands'))->json('data'))->pluck('slug');
        $this->assertNotContains('denso', $brands);
        $this->assertContains('rolon', $brands);

        $makes = collect($this->getJson($this->api('vehicles/manufacturers'))->json('data'))->pluck('name');
        $this->assertSame(['Royal Enfield'], $makes->values()->all());

        $search = $this->getJson($this->api('search?q=spark'))->assertOk()->json('data');
        $this->assertNotContains($this->carPart->name, collect($search)->pluck('name'));

        $home = $this->getJson($this->api('homepage'))->assertOk();
        $this->assertSame(['Arjun'], collect($home->json('data.testimonials'))->pluck('name')->all());
        $this->assertFalse($this->getJson($this->api('settings'))->json('data.sell_car_parts'));

        // A car part can't be bought from an old link or cart either.
        $this->postJson($this->api('cart/items'), ['product_id' => $this->carPart->id, 'quantity' => 1])->assertNotFound();
        $this->postJson($this->api('cart/items'), ['product_id' => $this->bikePart->id, 'quantity' => 1])->assertCreated();
    }

    public function test_admin_still_manages_car_parts_and_can_switch_them_back_on(): void
    {
        $this->actingAsStaff(Role::ADMIN);
        $this->assertContains($this->carPart->name, collect($this->getJson($this->api('admin/products?per_page=50'))->json('data'))->pluck('name'));

        $this->getJson($this->api('admin/settings'))->assertOk();
        $this->putJson($this->api('admin/settings'), ['settings' => ['sell_car_parts' => true]])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson($this->api("products/{$this->carPart->slug}"))->assertOk();
        $this->assertContains('car-parts', collect($this->getJson($this->api('categories'))->json('data'))->pluck('slug'));
    }

    public function test_migration_reclassifies_car_named_universal_products(): void
    {
        $kit = $this->product(['name' => 'Car First Aid Kit – 70 Pieces', 'vehicle_type' => 'universal', 'is_universal' => true]);
        $cloth = $this->universal;
        $post = \App\Models\BlogPost::create([
            'title' => 'Brake pads', 'slug' => 'brake-pads', 'status' => 'published', 'excerpt' => 'old',
            'content' => '<p>Brake pads are the single most important wear item on your car or bike, yet most of us forget them.</p>',
        ]);
        (require database_path('migrations/2026_10_13_000100_hide_car_catalogue.php'))->up();
        $this->assertSame('car', $kit->fresh()->vehicle_type->value ?? $kit->fresh()->vehicle_type);
        $this->assertSame('universal', $cloth->fresh()->vehicle_type->value ?? $cloth->fresh()->vehicle_type);
        $this->assertStringNotContainsString('car', $post->fresh()->content);
        $this->assertStringStartsWith('Brake pads are the single most important wear item on your bike', $post->fresh()->excerpt);
    }
}
