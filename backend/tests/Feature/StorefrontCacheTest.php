<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Cached storefront responses must be identical to fresh ones. Uses the file cache like
 * production (the array store used by other tests never serializes, so it hides the bug
 * where nested resources came back as objects and the mobile menu showed no sub-categories).
 */
class StorefrontCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'file', 'cache.stores.file.path' => storage_path('framework/cache/test-'.uniqid())]);
        Cache::store('file')->flush();
    }

    protected function tearDown(): void
    {
        Cache::store('file')->flush();
        parent::tearDown();
    }

    public function test_category_tree_keeps_children_as_a_list_when_served_from_cache(): void
    {
        $car = Category::factory()->create(['name' => 'Car Parts', 'slug' => 'car-parts', 'parent_id' => null]);
        Category::factory()->count(3)->create(['parent_id' => $car->id]);

        $fresh = $this->getJson(self::API.'/categories')->assertOk()->json('data');
        $cached = $this->getJson(self::API.'/categories')->assertOk()->json('data');

        $this->assertSame($fresh, $cached);
        $root = collect($cached)->firstWhere('slug', 'car-parts');
        $this->assertTrue(array_is_list($root['children']), 'children must be a JSON array');
        $this->assertCount(3, $root['children']);
        $this->assertArrayHasKey('slug', $root['children'][0]);
    }

    public function test_homepage_and_brands_are_the_same_from_cache(): void
    {
        Brand::factory()->count(2)->create();
        $this->assertSame($this->getJson(self::API.'/brands')->json('data'), $this->getJson(self::API.'/brands')->json('data'));
        $this->assertSame($this->getJson(self::API.'/homepage')->json('data'), $this->getJson(self::API.'/homepage')->json('data'));
    }
}
