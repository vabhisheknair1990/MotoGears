<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Test '.ucfirst(fake()->unique()->words(3, true));
        $mrp = fake()->numberBetween(500, 5000);

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => 'TST-'.strtoupper(Str::random(8)),
            'part_number' => 'PN-'.fake()->numerify('#####'),
            'short_description' => fake()->sentence(),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'cost_price' => round($mrp * 0.5),
            'mrp' => $mrp,
            'price' => round($mrp * 0.8),
            'tax_rate' => 18,
            'vehicle_type' => 'car',
            'is_universal' => false,
            'is_active' => true,
        ];
    }

    /** Give the product an inventory record with the given available stock. */
    public function withStock(int $qty = 50, int $threshold = 5): static
    {
        return $this->afterCreating(fn (Product $p) => Inventory::create([
            'product_id' => $p->id, 'sku' => $p->sku, 'quantity' => $qty, 'reserved' => 0, 'low_stock_threshold' => $threshold,
        ]));
    }

    public function priced(float $price, ?float $mrp = null): static
    {
        return $this->state(fn () => ['price' => $price, 'mrp' => $mrp ?? $price]);
    }

    public function universal(): static
    {
        return $this->state(fn () => ['is_universal' => true, 'vehicle_type' => 'universal']);
    }
}
