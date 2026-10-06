<?php

namespace App\Services;

use App\Models\Product;

/**
 * Maintains products.search_keywords — a denormalised blob (brand, category, part numbers,
 * tags, compatible vehicles) so one indexed LIKE / FULLTEXT query can answer searches such as
 * "thar led" or "classic 350 chain" without expensive joins.
 */
class ProductSearchIndexer
{
    public function index(Product $product): void
    {
        $product->loadMissing(['brand', 'category.parent', 'compatibilities.manufacturer', 'compatibilities.model', 'compatibilities.variant', 'attributeValues']);

        $parts = [
            $product->name,
            $product->sku,
            $product->part_number,
            $product->brand?->name,
            $product->category?->name,
            $product->category?->parent?->name,
            $product->vehicle_type,
            $product->is_universal ? 'universal all vehicles' : null,
            $product->position,
            $product->material,
            implode(' ', $product->tags ?? []),
            $product->attributeValues->pluck('value')->implode(' '),
        ];
        foreach ($product->compatibilities as $c) {
            $parts[] = $c->manufacturer?->name;
            $parts[] = $c->model?->name;
            $parts[] = $c->variant?->name;
            $parts[] = $c->variant?->fuel_type;
        }

        $text = mb_strtolower(preg_replace('/\s+/', ' ', implode(' ', array_unique(array_filter($parts)))));
        Product::whereKey($product->id)->update(['search_keywords' => $text]);
    }

    public function reindexAll(): int
    {
        $n = 0;
        Product::withTrashed()->with(['brand', 'category.parent', 'compatibilities.manufacturer', 'compatibilities.model', 'compatibilities.variant', 'attributeValues'])
            ->chunkById(200, function ($products) use (&$n) {
                foreach ($products as $p) {
                    $this->index($p);
                    $n++;
                }
            });

        return $n;
    }
}
