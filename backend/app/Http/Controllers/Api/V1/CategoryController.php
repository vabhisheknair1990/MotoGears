<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\StorefrontCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /** Full active category tree (cached; flushed automatically when categories change). */
    public function index(Request $request): JsonResponse
    {
        $tree = StorefrontCache::remember('storefront.category_tree', function () use ($request) {
            $roots = Category::active()->roots()->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->with(['children' => fn ($q) => $q->active()->withCount(['products' => fn ($p) => $p->where('is_active', true)])
                    ->with(['children' => fn ($c) => $c->active()])])
                ->orderBy('sort_order')->get();

            return CategoryResource::collection($roots)->resolve($request);
        });

        return $this->ok($tree, 'Categories retrieved successfully');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $category = Category::active()->where('slug', $slug)
            ->with(['parent', 'children' => fn ($q) => $q->active()->withCount(['products' => fn ($p) => $p->where('is_active', true)])])
            ->firstOrFail();

        $breadcrumbs = [];
        for ($c = $category; $c; $c = $c->parent_id ? Category::find($c->parent_id) : null) {
            array_unshift($breadcrumbs, ['name' => $c->name, 'slug' => $c->slug]);
        }

        return $this->ok(array_merge((new CategoryResource($category))->resolve($request), ['breadcrumbs' => $breadcrumbs]), 'Category retrieved successfully');
    }
}
