<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompatibilityRules;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\AdminProductResource;
use App\Http\Resources\CompatibilityResource;
use App\Http\Resources\ProductCardResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use App\Services\ProductAdminService;
use App\Services\ProductQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(
        private ProductAdminService $service,
        private ImageUploadService $uploads,
        private ProductQueryService $query,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'trashed'])],
            'stock' => ['nullable', Rule::in(['in_stock', 'low_stock', 'out_of_stock'])],
            'category' => ['nullable'], 'brand' => ['nullable'],
            'sort' => ['nullable', Rule::in(['newest', 'name', 'price_low', 'price_high', 'stock_low', 'best_selling'])],
        ]);

        $q = Product::query()->select('products.*')->with(ProductQueryService::CARD_RELATIONS)
            ->when($request->status === 'trashed', fn ($w) => $w->onlyTrashed())
            ->when($request->status === 'active', fn ($w) => $w->where('is_active', true))
            ->when($request->status === 'inactive', fn ($w) => $w->where('is_active', false));
        $this->query->applyFilters($q, $request->only(['search', 'category', 'brand']));

        match ($request->stock) {
            'in_stock' => $q->whereHas('inventory', fn ($i) => $i->whereRaw('(quantity - reserved) > low_stock_threshold')),
            'low_stock' => $q->whereHas('inventory', fn ($i) => $i->lowStock()),
            'out_of_stock' => $q->whereHas('inventory', fn ($i) => $i->outOfStock()),
            default => null,
        };
        match ($request->sort) {
            'name' => $q->orderBy('name'),
            'price_low' => $q->orderBy('price'),
            'price_high' => $q->orderByDesc('price'),
            'best_selling' => $q->orderByDesc('sold_count'),
            'stock_low' => $q->leftJoin('inventories', 'inventories.product_id', '=', 'products.id')->orderByRaw('(inventories.quantity - inventories.reserved) asc'),
            default => $q->latest('products.id'),
        };

        $page = $q->paginate($this->perPage(20));

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $page->getCollection()->map(fn (Product $p) => array_merge((new ProductCardResource($p))->resolve($request), [
                'is_active' => (bool) $p->is_active,
                'sold_count' => (int) $p->sold_count,
                'stock' => $p->inventory ? ['quantity' => $p->inventory->quantity, 'reserved' => $p->inventory->reserved, 'available' => $p->inventory->available()] : null,
                'deleted_at' => $p->deleted_at?->toIso8601String(),
                'updated_at' => $p->updated_at?->toIso8601String(),
            ]))->values(),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::withTrashed()->with($this->detailRelations())->findOrFail($id);

        return $this->ok(new AdminProductResource($product), 'Product retrieved successfully');
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = $this->service->create($request->validated(), $request->user());

        return $this->created(new AdminProductResource($product->fresh()->load($this->detailRelations())), 'Product created successfully');
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $this->service->update($product, $request->validated(), $request->user());

        return $this->ok(new AdminProductResource($product->fresh()->load($this->detailRelations())), 'Product updated successfully');
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);
        $product->delete();
        $this->audit->log('product.deleted', $product, null, null, $request->user());

        return $this->deleted('Product moved to trash');
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $product->restore();
        $this->audit->log('product.restored', $product, null, null, $request->user());

        return $this->ok(new AdminProductResource($product->load($this->detailRelations())), 'Product restored');
    }

    public function toggle(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate(['is_active' => ['sometimes', 'boolean'], 'is_featured' => ['sometimes', 'boolean']]);
        $product->update($data);
        $this->audit->changes('product.updated', $product, $request->user());

        return $this->ok(['id' => $product->id, 'is_active' => $product->is_active, 'is_featured' => $product->is_featured], 'Product updated');
    }

    /** POST /admin/products/{id}/images — multipart: images[] (one or more files) */
    public function uploadImages(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ImageUploadService::rules(true),
            'alt' => ['nullable', 'string', 'max:190'],
        ]);
        if ($product->images()->count() + count($request->file('images')) > 15) {
            return response()->json(['success' => false, 'message' => 'A product can have at most 15 images.'], 422);
        }

        DB::transaction(function () use ($request, $product) {
            $hasPrimary = $product->images()->where('is_primary', true)->exists();
            $order = (int) $product->images()->max('sort_order');
            foreach ($request->file('images') as $i => $file) {
                $product->images()->create([
                    'path' => $this->uploads->store($file, 'products'),
                    'alt' => $request->alt ?: $product->name,
                    'is_primary' => ! $hasPrimary && $i === 0,
                    'sort_order' => ++$order,
                ]);
            }
        });
        $product->touch();

        return $this->created($this->imagesPayload($product), 'Images uploaded successfully');
    }

    public function updateImage(Request $request, Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $data = $request->validate(['is_primary' => ['sometimes', 'boolean'], 'alt' => ['nullable', 'string', 'max:190'], 'sort_order' => ['sometimes', 'integer', 'min:0']]);
        DB::transaction(function () use ($product, $image, $data) {
            if (! empty($data['is_primary'])) {
                $product->images()->update(['is_primary' => false]);
            }
            $image->update($data);
        });
        $product->touch();

        return $this->ok($this->imagesPayload($product), 'Image updated');
    }

    public function reorderImages(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);
        foreach ($data['order'] as $i => $id) {
            $product->images()->whereKey($id)->update(['sort_order' => $i]);
        }

        return $this->ok($this->imagesPayload($product), 'Images reordered');
    }

    public function deleteImage(Product $product, ProductImage $image): JsonResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $wasPrimary = $image->is_primary;
        $this->uploads->delete($image->path);
        $image->delete();
        if ($wasPrimary) {
            $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }
        $product->touch();

        return $this->ok($this->imagesPayload($product), 'Image deleted');
    }

    public function compatibilities(Product $product): JsonResponse
    {
        return $this->ok(CompatibilityResource::collection($product->compatibilities()->with(['manufacturer', 'model', 'variant'])->get()), 'Compatibility retrieved');
    }

    /** PUT /admin/products/{id}/compatibilities — replaces the full list. */
    public function syncCompatibilities(Request $request, Product $product): JsonResponse
    {
        $request->validate(array_merge(
            ['compatibilities' => ['present', 'array', 'max:200'], 'is_universal' => ['sometimes', 'boolean']],
            CompatibilityRules::rules('compatibilities.*.'),
        ));
        if ($request->has('is_universal')) {
            $product->update(['is_universal' => $request->boolean('is_universal')]);
        }
        $this->service->syncCompatibilities($product, $request->input('compatibilities', []));
        $this->audit->log('product.compatibility_updated', $product, null, ['rows' => count($request->input('compatibilities', []))], $request->user());

        return $this->ok(CompatibilityResource::collection($product->compatibilities()->with(['manufacturer', 'model', 'variant'])->get()), 'Compatibility saved');
    }

    private function imagesPayload(Product $product): array
    {
        return $product->images()->get()->map(fn (ProductImage $i) => [
            'id' => $i->id, 'url' => $i->url(), 'alt' => $i->alt, 'is_primary' => $i->is_primary, 'sort_order' => $i->sort_order,
        ])->all();
    }

    private function detailRelations(): array
    {
        return ['brand', 'category.parent', 'images', 'primaryImage', 'variants', 'faqs', 'inventory', 'attributeValues', 'compatibilities.manufacturer', 'compatibilities.model', 'compatibilities.variant'];
    }
}
