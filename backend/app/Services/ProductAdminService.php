<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCompatibility;
use App\Models\User;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductAdminService
{
    private const RELATION_KEYS = ['attribute_value_ids', 'faqs', 'variants', 'compatibilities', 'initial_stock', 'low_stock_threshold', 'allow_backorder'];

    public function __construct(
        private SlugService $slugs,
        private InventoryService $inventory,
        private ProductSearchIndexer $indexer,
        private AuditLogger $audit,
    ) {}

    public function create(array $data, ?User $by): Product
    {
        return DB::transaction(function () use ($data, $by) {
            $attrs = Arr::except($data, self::RELATION_KEYS);
            $attrs['slug'] = $data['slug'] ?? $this->slugs->unique(Product::class, $data['name']);
            $attrs['meta_title'] = $attrs['meta_title'] ?? $data['name'];

            $product = Product::create($attrs);
            $this->syncRelations($product, $data);
            $this->inventory->setInitialStock($product, (int) ($data['initial_stock'] ?? 0), (int) ($data['low_stock_threshold'] ?? 5), (bool) ($data['allow_backorder'] ?? false), $by);
            $this->indexer->index($product->fresh());
            $this->audit->log('product.created', $product, null, $attrs, $by);

            return $product;
        });
    }

    public function update(Product $product, array $data, ?User $by): Product
    {
        return DB::transaction(function () use ($product, $data, $by) {
            $attrs = Arr::except($data, self::RELATION_KEYS);
            if (array_key_exists('slug', $attrs) && ! $attrs['slug']) {
                $attrs['slug'] = $this->slugs->unique(Product::class, $attrs['name'] ?? $product->name, $product->id);
            }
            $product->update($attrs);
            $this->audit->changes('product.updated', $product, $by);
            $this->syncRelations($product, $data);

            $inv = $this->inventory->forProduct($product);
            $inv->fill(array_filter([
                'sku' => $product->sku,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
                'allow_backorder' => $data['allow_backorder'] ?? null,
            ], fn ($v) => $v !== null))->save();

            $this->indexer->index($product->fresh());

            return $product;
        });
    }

    /**
     * Replace the product's compatibility list. Each row may target a whole manufacturer, a model
     * (all variants) or one variant, optionally limited to a year range. Parent IDs are derived
     * from the most specific ID supplied so inconsistent input can't create orphan rows.
     */
    public function syncCompatibilities(Product $product, array $rows): void
    {
        $normalised = collect($rows)->map(function (array $r) {
            if (! empty($r['vehicle_variant_id'])) {
                $variant = VehicleVariant::with('model')->findOrFail($r['vehicle_variant_id']);
                $r['vehicle_model_id'] = $variant->vehicle_model_id;
                $r['vehicle_manufacturer_id'] = $variant->model->vehicle_manufacturer_id;
            } elseif (! empty($r['vehicle_model_id'])) {
                $r['vehicle_manufacturer_id'] = VehicleModel::findOrFail($r['vehicle_model_id'])->vehicle_manufacturer_id;
            }

            return [
                'vehicle_manufacturer_id' => (int) $r['vehicle_manufacturer_id'],
                'vehicle_model_id' => $r['vehicle_model_id'] ?? null ?: null,
                'vehicle_variant_id' => $r['vehicle_variant_id'] ?? null ?: null,
                'year_from' => $r['year_from'] ?? null ?: null,
                'year_to' => $r['year_to'] ?? null ?: null,
                'notes' => $r['notes'] ?? null,
            ];
        })->unique(fn ($r) => implode('|', [$r['vehicle_manufacturer_id'], $r['vehicle_model_id'], $r['vehicle_variant_id'], $r['year_from'], $r['year_to']]));

        $product->compatibilities()->delete();
        foreach ($normalised as $row) {
            $product->compatibilities()->create($row);
        }
        $this->indexer->index($product->fresh());
    }

    private function syncRelations(Product $product, array $data): void
    {
        if (array_key_exists('attribute_value_ids', $data)) {
            $product->attributeValues()->sync($data['attribute_value_ids'] ?? []);
        }
        if (array_key_exists('faqs', $data)) {
            $product->faqs()->delete();
            foreach (array_values($data['faqs'] ?? []) as $i => $faq) {
                $product->faqs()->create(['question' => $faq['question'], 'answer' => $faq['answer'], 'sort_order' => $i]);
            }
        }
        if (array_key_exists('variants', $data)) {
            $keep = [];
            foreach (array_values($data['variants'] ?? []) as $i => $v) {
                $variant = $product->variants()->updateOrCreate(
                    ['id' => $v['id'] ?? null],
                    ['sku' => strtoupper($v['sku']), 'name' => $v['name'], 'options' => $v['options'] ?? null,
                        'price_adjustment' => $v['price_adjustment'] ?? 0, 'is_active' => $v['is_active'] ?? true, 'sort_order' => $i]
                );
                $keep[] = $variant->id;
            }
            $product->variants()->whereNotIn('id', $keep)->delete();
        }
        if (array_key_exists('compatibilities', $data)) {
            $this->syncCompatibilities($product, $data['compatibilities'] ?? []);
        }
    }
}
