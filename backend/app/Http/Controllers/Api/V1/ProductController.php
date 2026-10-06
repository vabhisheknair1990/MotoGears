<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ProductIndexRequest;
use App\Http\Resources\CompatibilityResource;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\VehicleVariant;
use App\Services\ProductQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductQueryService $products) {}

    /**
     * GET /products?category=&brand=&vehicle_variant=&min_price=&max_price=&rating=&in_stock=&sort=&page=
     */
    public function index(ProductIndexRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $page = $this->products->query($filters)->paginate($this->perPage(20, 60))->withQueryString();

        $extra = ['sort' => $filters['sort'] ?? null];
        if ($request->boolean('with_facets')) {
            $extra['facets'] = $this->products->facets($filters);
        }
        if (! empty($filters['vehicle_variant']) && ($v = VehicleVariant::with('model.manufacturer')->find($filters['vehicle_variant']))) {
            $extra['vehicle'] = ['id' => $v->id, 'name' => $v->fullName(), 'year_range' => $v->yearRange()];
        }

        return $this->paginated($page, ProductCardResource::class, 'Products retrieved successfully', $extra);
    }

    /** Facets (brands, categories, price range, attributes) for the current filter set. */
    public function filters(ProductIndexRequest $request): JsonResponse
    {
        return $this->ok($this->products->facets($request->filters()), 'Filters retrieved successfully');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $product = Product::active()->where('slug', $slug)->with([
            'brand', 'category.parent', 'images', 'variants', 'faqs', 'inventory', 'primaryImage',
            'attributeValues',
            'compatibilities' => fn ($q) => $q->with(['manufacturer', 'model', 'variant'])->orderBy('vehicle_manufacturer_id'),
        ])->firstOrFail();

        Product::whereKey($product->id)->increment('view_count');

        $data = (new ProductResource($product))->resolve($request);
        if ($variantId = $request->integer('vehicle_variant')) {
            $variant = VehicleVariant::with('model.manufacturer')->find($variantId);
            $data['fits_selected_vehicle'] = $variant ? [
                'vehicle' => $variant->fullName(),
                'fits' => $product->fitsVariant($variant, $request->integer('vehicle_year') ?: null),
            ] : null;
        }

        $crumbs = [['name' => 'Shop', 'slug' => null]];
        if ($product->category->parent) {
            $crumbs[] = ['name' => $product->category->parent->name, 'slug' => $product->category->parent->slug];
        }
        $crumbs[] = ['name' => $product->category->name, 'slug' => $product->category->slug];
        $data['breadcrumbs'] = $crumbs;

        return $this->ok($data, 'Product retrieved successfully');
    }

    public function compatibility(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);
        $rows = $product->compatibilities()->with(['manufacturer', 'model', 'variant'])->get()
            ->sortBy(fn ($c) => [$c->manufacturer?->name, $c->model?->name, $c->variant?->name])->values();

        return $this->ok([
            'is_universal' => $product->is_universal,
            'vehicle_type' => $product->vehicle_type,
            'vehicles' => CompatibilityResource::collection($rows)->resolve(request()),
            'grouped' => $rows->groupBy(fn ($c) => $c->manufacturer?->name)->map(fn ($g, $make) => [
                'manufacturer' => $make,
                'models' => $g->groupBy(fn ($c) => $c->model?->name ?? 'All models')->map(fn ($m, $model) => [
                    'model' => $model,
                    'variants' => $m->map(fn ($c) => $c->variant?->name ?? 'All variants')->unique()->values(),
                    'years' => $m->map(fn ($c) => ($c->year_from ?? '').'–'.($c->year_to ?? 'present'))->unique()->values(),
                ])->values(),
            ])->values(),
        ], 'Compatibility retrieved successfully');
    }

    public function checkCompatibility(Request $request, Product $product): JsonResponse
    {
        $request->validate(['vehicle_variant_id' => ['required', 'integer', 'exists:vehicle_variants,id'], 'year' => ['nullable', 'integer']]);
        $variant = VehicleVariant::with('model.manufacturer')->findOrFail($request->vehicle_variant_id);
        $fits = $product->fitsVariant($variant, $request->integer('year') ?: null);

        return $this->ok([
            'fits' => $fits,
            'vehicle' => $variant->fullName(),
            'message' => $fits ? "Fits your {$variant->fullName()}" : "Does not fit your {$variant->fullName()}",
        ], 'Compatibility checked');
    }

    public function related(Product $product): JsonResponse
    {
        $related = Product::active()->with(ProductQueryService::CARD_RELATIONS)
            ->whereKeyNot($product->id)
            ->where(fn ($q) => $q->where('category_id', $product->category_id)->orWhere('brand_id', $product->brand_id))
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$product->category_id])
            ->orderByDesc('sold_count')->limit(8)->get();

        return $this->ok(ProductCardResource::collection($related), 'Related products retrieved');
    }
}
