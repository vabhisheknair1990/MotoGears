<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCompatibility;
use App\Models\VehicleVariant;
use App\Services\ProductSearchIndexer;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    public function test_product_listing_is_paginated_with_standard_meta(): void
    {
        Product::factory()->count(25)->withStock()->create();

        $this->getJson($this->api('products?per_page=10&page=2'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'brand', 'image', 'mrp', 'price', 'discount_percent', 'rating', 'review_count', 'stock_status']]]);
    }

    public function test_inactive_products_are_hidden(): void
    {
        $this->product(['is_active' => false, 'slug' => 'hidden-part']);
        $this->getJson($this->api('products'))->assertJsonPath('meta.total', 0);
        $this->getJson($this->api('products/hidden-part'))->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_filters_by_category_brand_price_and_stock(): void
    {
        $parent = Category::factory()->create(['slug' => 'brakes-root']);
        $child = Category::factory()->create(['slug' => 'brake-pads', 'parent_id' => $parent->id]);
        $bosch = Brand::factory()->create(['slug' => 'bosch']);
        $a = $this->product(['category_id' => $child->id, 'brand_id' => $bosch->id, 'price' => 1500, 'mrp' => 2000]);
        $this->product(['category_id' => $child->id, 'price' => 6000, 'mrp' => 6000]);
        $this->product(['brand_id' => $bosch->id, 'price' => 900, 'mrp' => 900], stock: 0);

        // Parent category includes children.
        $this->getJson($this->api('products?category=brakes-root'))->assertJsonPath('meta.total', 2);
        $this->getJson($this->api('products?category=brake-pads&brand=bosch'))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $a->id);
        $this->getJson($this->api('products?min_price=1000&max_price=5000'))->assertJsonPath('meta.total', 1);
        $this->getJson($this->api('products?brand=bosch&in_stock=1'))->assertJsonPath('meta.total', 1);
        $this->getJson($this->api('products?discount=20'))->assertJsonPath('meta.total', 1);
    }

    public function test_sorting(): void
    {
        $this->product(['price' => 300, 'mrp' => 300]);
        $this->product(['price' => 100, 'mrp' => 100]);
        $this->product(['price' => 200, 'mrp' => 200]);

        $prices = collect($this->getJson($this->api('products?sort=price_low'))->json('data'))->pluck('price')->all();
        $this->assertSame([100, 200, 300], $prices);
        $prices = collect($this->getJson($this->api('products?sort=price_high'))->json('data'))->pluck('price')->all();
        $this->assertSame([300, 200, 100], $prices);
    }

    public function test_vehicle_compatibility_filter_and_endpoint(): void
    {
        $variant = VehicleVariant::factory()->create(['year_from' => 2020]);
        $otherVariant = VehicleVariant::factory()->create(['vehicle_model_id' => $variant->vehicle_model_id, 'name' => 'Other', 'year_from' => 2015, 'year_to' => 2018]);
        $exact = $this->product(['name' => 'Exact fit']);
        $modelWide = $this->product(['name' => 'Model wide']);
        $universal = $this->product(['name' => 'Universal', 'is_universal' => true, 'vehicle_type' => 'universal']);
        $this->product(['name' => 'No fit']);
        $oldYears = $this->product(['name' => 'Old years']);

        ProductCompatibility::create(['product_id' => $exact->id, 'vehicle_manufacturer_id' => $variant->model->vehicle_manufacturer_id, 'vehicle_model_id' => $variant->vehicle_model_id, 'vehicle_variant_id' => $variant->id]);
        ProductCompatibility::create(['product_id' => $modelWide->id, 'vehicle_manufacturer_id' => $variant->model->vehicle_manufacturer_id, 'vehicle_model_id' => $variant->vehicle_model_id]);
        ProductCompatibility::create(['product_id' => $oldYears->id, 'vehicle_manufacturer_id' => $variant->model->vehicle_manufacturer_id, 'vehicle_model_id' => $variant->vehicle_model_id, 'year_from' => 2012, 'year_to' => 2016]);

        $ids = collect($this->getJson($this->api("products?vehicle_variant_id={$variant->id}"))->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertEquals(collect([$exact->id, $modelWide->id, $universal->id])->sort()->values()->all(), $ids);

        $ids = collect($this->getJson($this->api("products?vehicle_variant={$otherVariant->id}"))->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($oldYears->id));
        $this->assertFalse($ids->contains($exact->id));

        $this->getJson($this->api("products/{$exact->id}/compatibility"))->assertOk()->assertJsonCount(1, 'data.vehicles');
        $this->getJson($this->api("products/{$exact->id}/check-compatibility?vehicle_variant_id={$variant->id}"))->assertJsonPath('data.fits', true);
        $this->getJson($this->api("products/{$exact->id}/check-compatibility?vehicle_variant_id={$otherVariant->id}"))->assertJsonPath('data.fits', false);
    }

    public function test_product_detail_by_slug(): void
    {
        $p = $this->product(['slug' => 'bosch-premium-brake-pad', 'specifications' => [['label' => 'Axle', 'value' => 'Front']]]);

        $this->getJson($this->api('products/bosch-premium-brake-pad'))
            ->assertOk()
            ->assertJsonPath('data.id', $p->id)
            ->assertJsonPath('data.specifications.0.value', 'Front')
            ->assertJsonStructure(['data' => ['images', 'compatibility', 'faqs', 'inventory', 'breadcrumbs', 'brand', 'category']])
            ->assertJsonMissingPath('data.cost_price');
    }

    public function test_search_and_suggestions(): void
    {
        $p = $this->product(['name' => 'Mahindra Thar LED Headlight Kit', 'sku' => 'MG-LGT-0001', 'part_number' => 'XV-777']);
        $this->product(['name' => 'Bosch Wiper Blade']);
        app(ProductSearchIndexer::class)->reindexAll();

        $this->getJson($this->api('search?q=thar+led'));
        $this->getJson($this->api('search?q=thar+led'))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $p->id);
        $this->getJson($this->api('search?q=XV-777'))->assertJsonPath('meta.total', 1);
        $this->getJson($this->api('search?q=MG-LGT-0001'))->assertJsonPath('data.0.id', $p->id);
        $this->getJson($this->api('search/suggestions?q=thar'))->assertOk()->assertJsonCount(1, 'data.products');
        $this->getJson($this->api('search/popular'))->assertOk()->assertJsonPath('data.0', 'thar led');
        $this->getJson($this->api('search'))->assertStatus(422);
    }

    public function test_category_tree_and_brands(): void
    {
        $root = Category::factory()->create(['parent_id' => null]);
        Category::factory()->count(2)->create(['parent_id' => $root->id]);
        Brand::factory()->count(3)->create();

        $this->getJson($this->api('categories'))->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(2, 'data.0.children');
        $this->getJson($this->api("categories/{$root->slug}"))->assertOk()->assertJsonPath('data.breadcrumbs.0.slug', $root->slug);
        $this->getJson($this->api('brands'))->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_vehicle_selector_endpoints(): void
    {
        $variant = VehicleVariant::factory()->create(['year_from' => 2021, 'year_to' => 2023]);
        $m = $variant->model;

        $this->getJson($this->api('vehicles/manufacturers'))->assertOk()->assertJsonPath('data.0.id', $m->vehicle_manufacturer_id);
        $this->getJson($this->api("vehicles/models?manufacturer_id={$m->vehicle_manufacturer_id}"))->assertJsonPath('data.0.id', $m->id);
        $this->getJson($this->api("vehicles/years?model_id={$m->id}"))->assertJsonPath('data', [2023, 2022, 2021]);
        $this->getJson($this->api("vehicles/variants?model_id={$m->id}&year=2022"))->assertJsonCount(1, 'data');
        $this->getJson($this->api("vehicles/variants?model_id={$m->id}&year=2019"))->assertJsonCount(0, 'data');
        $this->getJson($this->api('vehicles/models'))->assertStatus(422);
    }

    public function test_homepage_aggregates_sections(): void
    {
        $this->product(['is_featured' => true]);

        $this->getJson($this->api('homepage'))->assertOk()->assertJsonStructure(['data' => [
            'hero_banners', 'featured_categories', 'featured_products', 'best_sellers', 'new_arrivals', 'brands', 'offers', 'testimonials', 'blog_posts',
        ]])->assertJsonCount(1, 'data.featured_products');
    }
}
