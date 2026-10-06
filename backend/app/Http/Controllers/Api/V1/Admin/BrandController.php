<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class BrandController extends Controller
{
    public function __construct(private SlugService $slugs, private ImageUploadService $uploads, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,inactive']]);
        $page = Brand::withCount('products')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->when($request->status, fn ($q, $s) => $q->where('is_active', $s === 'active'))
            ->orderBy('sort_order')->orderBy('name')->paginate($this->perPage(50, 200));

        return $this->paginated($page, BrandResource::class, 'Brands retrieved successfully');
    }

    public function show(Brand $brand): JsonResponse
    {
        return $this->ok(new BrandResource($brand->loadCount('products')), 'Brand retrieved successfully');
    }

    public function store(BrandRequest $request): JsonResponse
    {
        $data = Arr::except($request->validated(), ['logo', 'remove_logo']);
        $data['slug'] = $data['slug'] ?? $this->slugs->unique(Brand::class, $data['name']);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $this->uploads->store($request->file('logo'), 'brands');
        }
        $brand = Brand::create($data);
        $this->audit->log('brand.created', $brand, null, $data);

        return $this->created(new BrandResource($brand), 'Brand created successfully');
    }

    public function update(BrandRequest $request, Brand $brand): JsonResponse
    {
        $data = Arr::except($request->validated(), ['logo', 'remove_logo']);
        if (array_key_exists('slug', $data) && ! $data['slug']) {
            unset($data['slug']);
        }
        if ($request->hasFile('logo')) {
            $this->uploads->delete($brand->logo_path);
            $data['logo_path'] = $this->uploads->store($request->file('logo'), 'brands');
        } elseif ($request->boolean('remove_logo')) {
            $this->uploads->delete($brand->logo_path);
            $data['logo_path'] = null;
        }
        $brand->update($data);
        $this->audit->changes('brand.updated', $brand);

        return $this->ok(new BrandResource($brand->fresh()), 'Brand updated successfully');
    }

    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->exists()) {
            return response()->json(['success' => false, 'message' => 'This brand still has products. Reassign or delete them first.'], 422);
        }
        $brand->delete();
        $this->audit->log('brand.deleted', $brand);

        return $this->deleted('Brand deleted');
    }
}
