<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\StorefrontCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $brands = StorefrontCache::remember('storefront.brands', fn () => BrandResource::collection(
            Brand::active()->withCount(['products' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->orderBy('name')->get()
        )->resolve($request));

        return $this->ok($brands, 'Brands retrieved successfully');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $brand = Brand::active()->where('slug', $slug)->withCount(['products' => fn ($q) => $q->where('is_active', true)])->firstOrFail();

        return $this->ok(new BrandResource($brand), 'Brand retrieved successfully');
    }
}
