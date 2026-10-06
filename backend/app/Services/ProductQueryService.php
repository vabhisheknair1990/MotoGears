<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\VehicleVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Builds catalogue queries from storefront filters. Used by the product listing,
 * category / brand pages and search so they all behave identically.
 */
class ProductQueryService
{
    public const SORTS = [
        'relevance' => 'Relevance',
        'popular' => 'Most popular',
        'newest' => 'Newest',
        'price_low' => 'Price: low to high',
        'price_high' => 'Price: high to low',
        'rating' => 'Top rated',
        'discount' => 'Biggest discount',
    ];

    /** Eager loads needed by ProductCardResource. */
    public const CARD_RELATIONS = ['brand:id,name,slug', 'category:id,name,slug', 'inventory:id,product_id,quantity,reserved,low_stock_threshold,allow_backorder', 'primaryImage'];

    public function query(array $f, bool $includeInactive = false): Builder
    {
        $q = Product::query()->select('products.*')->with(self::CARD_RELATIONS);
        if (! $includeInactive) {
            $q->active();
        }

        $this->applyFilters($q, $f);
        $this->applySort($q, $f['sort'] ?? (! empty($f['search']) ? 'relevance' : 'popular'), $f['search'] ?? null);

        return $q;
    }

    public function applyFilters(Builder $q, array $f, array $except = []): Builder
    {
        $has = fn (string $k) => ! in_array($k, $except, true) && isset($f[$k]) && $f[$k] !== '' && $f[$k] !== null && $f[$k] !== [];

        if ($has('search')) {
            $this->applySearch($q, (string) $f['search']);
        }
        if ($has('category')) {
            $ids = collect(Arr::wrap($f['category']))->flatMap(function ($c) {
                $cat = is_numeric($c) ? Category::find($c) : Category::where('slug', $c)->first();

                return $cat ? $cat->descendantIds() : [0];
            })->unique()->all();
            $q->whereIn('products.category_id', $ids);
        }
        if ($has('brand')) {
            $brands = collect(is_array($f['brand']) ? $f['brand'] : explode(',', (string) $f['brand']));
            $ids = Brand::whereIn('slug', $brands)->orWhereIn('id', $brands->filter(fn ($b) => is_numeric($b)))->pluck('id');
            $q->whereIn('products.brand_id', $ids->isEmpty() ? [0] : $ids);
        }
        if ($has('vehicle_variant')) {
            $variant = VehicleVariant::with('model')->find($f['vehicle_variant']);
            $variant ? $q->compatibleWith($variant, isset($f['vehicle_year']) ? (int) $f['vehicle_year'] : null) : $q->whereRaw('1 = 0');
        } elseif ($has('vehicle_model')) {
            $modelId = (int) $f['vehicle_model'];
            $q->where(fn ($w) => $w->where('products.is_universal', true)->orWhereHas('compatibilities', fn ($c) => $c->where('vehicle_model_id', $modelId)
                ->orWhere(fn ($m) => $m->whereNull('vehicle_model_id')->whereIn('vehicle_manufacturer_id', DB::table('vehicle_models')->where('id', $modelId)->select('vehicle_manufacturer_id')))));
        } elseif ($has('vehicle_manufacturer')) {
            $mid = (int) $f['vehicle_manufacturer'];
            $q->where(fn ($w) => $w->where('products.is_universal', true)->orWhereHas('compatibilities', fn ($c) => $c->where('vehicle_manufacturer_id', $mid)));
        }
        if ($has('vehicle_type')) {
            $q->whereIn('products.vehicle_type', [$f['vehicle_type'], 'universal']);
        }
        if ($has('min_price')) {
            $q->where('products.price', '>=', (float) $f['min_price']);
        }
        if ($has('max_price')) {
            $q->where('products.price', '<=', (float) $f['max_price']);
        }
        if ($has('rating')) {
            $q->where('products.rating_avg', '>=', (float) $f['rating']);
        }
        if ($has('discount')) {
            $q->whereRaw('products.mrp > 0 AND (products.mrp - products.price) * 100.0 >= ? * products.mrp', [(float) $f['discount']]);
        }
        if ($has('in_stock') && filter_var($f['in_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $q->whereHas('inventory', fn ($i) => $i->whereRaw('(quantity - reserved) > 0')->orWhere('allow_backorder', true));
        }
        if ($has('featured') && filter_var($f['featured'], FILTER_VALIDATE_BOOLEAN)) {
            $q->where('products.is_featured', true);
        }
        if ($has('attributes') && is_array($f['attributes'])) {
            // attributes[position]=front,rear  → must match one value in every attribute group
            foreach ($f['attributes'] as $attrSlug => $values) {
                $slugs = is_array($values) ? $values : explode(',', (string) $values);
                $q->whereHas('attributeValues', fn ($av) => $av->whereIn('attribute_values.slug', $slugs)
                    ->whereHas('attribute', fn ($a) => $a->where('slug', $attrSlug)));
            }
        }
        if ($has('ids')) {
            $q->whereIn('products.id', is_array($f['ids']) ? $f['ids'] : explode(',', (string) $f['ids']));
        }

        return $q;
    }

    public function applySearch(Builder $q, string $term): void
    {
        $tokens = $this->tokens($term);
        if (! $tokens) {
            return;
        }
        foreach ($tokens as $t) {
            $like = '%'.$this->escapeLike($t).'%';
            $q->where(fn ($w) => $w->where('products.name', 'like', $like)
                ->orWhere('products.sku', 'like', $like)
                ->orWhere('products.part_number', 'like', $like)
                ->orWhere('products.search_keywords', 'like', $like));
        }
    }

    public function applySort(Builder $q, string $sort, ?string $search = null): void
    {
        match ($sort) {
            'price_low' => $q->orderBy('products.price')->orderBy('products.id'),
            'price_high' => $q->orderByDesc('products.price')->orderBy('products.id'),
            'newest' => $q->orderByDesc('products.created_at')->orderByDesc('products.id'),
            'rating' => $q->orderByDesc('products.rating_avg')->orderByDesc('products.rating_count'),
            'discount' => $q->orderByRaw('CASE WHEN products.mrp > 0 THEN (products.mrp - products.price) * 1.0 / products.mrp ELSE 0 END DESC'),
            'relevance' => $search ? $this->orderByRelevance($q, $search) : $q->orderByDesc('products.sold_count'),
            default => $q->orderByDesc('products.is_featured')->orderByDesc('products.sold_count')->orderBy('products.id'),
        };
    }

    private function orderByRelevance(Builder $q, string $search): void
    {
        $term = mb_strtolower(trim($search));
        $like = $this->escapeLike($term);
        $q->orderByRaw(
            'CASE WHEN LOWER(products.sku) = ? OR LOWER(products.part_number) = ? THEN 0
                  WHEN LOWER(products.name) LIKE ? THEN 1
                  WHEN LOWER(products.name) LIKE ? THEN 2
                  ELSE 3 END',
            [$term, $term, $like.'%', '%'.$like.'%']
        )->orderByDesc('products.sold_count');
    }

    /**
     * Facet counts for the filter sidebar, computed against the current filter set
     * (each facet ignores its own filter so options don't disappear when selected).
     */
    public function facets(array $f): array
    {
        $base = fn (array $except) => $this->applyFilters(Product::query()->active(), $f, $except);

        $brands = $base(['brand'])->join('brands', 'brands.id', '=', 'products.brand_id')
            ->groupBy('brands.id', 'brands.name', 'brands.slug')
            ->orderBy('brands.name')
            ->get([DB::raw('brands.id as id'), DB::raw('brands.name as name'), DB::raw('brands.slug as slug'), DB::raw('COUNT(products.id) as count')]);

        $categories = $base(['category'])->join('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderBy('categories.name')
            ->get([DB::raw('categories.id as id'), DB::raw('categories.name as name'), DB::raw('categories.slug as slug'), DB::raw('COUNT(products.id) as count')]);

        $price = $base(['min_price', 'max_price'])->selectRaw('MIN(products.price) as min, MAX(products.price) as max')->first();

        $attributes = DB::table('product_attributes')
            ->join('attribute_values', 'attribute_values.id', '=', 'product_attributes.attribute_value_id')
            ->join('attributes', 'attributes.id', '=', 'attribute_values.attribute_id')
            ->whereIn('product_attributes.product_id', $base(['attributes'])->select('products.id'))
            ->where('attributes.is_filterable', true)
            ->groupBy('attributes.id', 'attributes.name', 'attributes.slug', 'attribute_values.id', 'attribute_values.value', 'attribute_values.slug')
            ->orderBy('attributes.sort_order')->orderBy('attribute_values.value')
            ->get(['attributes.name as attribute', 'attributes.slug as attribute_slug', 'attribute_values.value as value', 'attribute_values.slug as slug', DB::raw('COUNT(*) as count')])
            ->groupBy('attribute_slug')
            ->map(fn ($rows) => ['name' => $rows[0]->attribute, 'slug' => $rows[0]->attribute_slug, 'values' => $rows->map(fn ($r) => ['value' => $r->value, 'slug' => $r->slug, 'count' => (int) $r->count])->values()])
            ->values();

        return [
            'brands' => $brands->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'slug' => $b->slug, 'count' => (int) $b->count]),
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'count' => (int) $c->count]),
            'price' => ['min' => (float) ($price->min ?? 0), 'max' => (float) ($price->max ?? 0)],
            'ratings' => [4, 3, 2],
            'discounts' => [10, 20, 30, 50],
            'attributes' => $attributes,
            'sorts' => collect(self::SORTS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ];
    }

    public function tokens(string $term): array
    {
        $term = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s\-\.\/]/u', ' ', $term)));

        return array_values(array_filter(array_slice(preg_split('/\s+/', $term), 0, 8), fn ($t) => mb_strlen($t) >= 1));
    }

    private function escapeLike(string $v): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v);
    }
}
