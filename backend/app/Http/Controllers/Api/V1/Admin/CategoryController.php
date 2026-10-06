<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CategoryController extends Controller
{
    public function __construct(private SlugService $slugs, private ImageUploadService $uploads, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'parent_id' => ['nullable'], 'tree' => ['nullable']]);
        if ($request->boolean('tree')) {
            $roots = Category::roots()->withCount('products')->with(['children' => fn ($q) => $q->withCount('products')->with(['children' => fn ($c) => $c->withCount('products')])])->orderBy('sort_order')->get();

            return $this->ok(CategoryResource::collection($roots), 'Categories retrieved successfully');
        }
        $page = Category::with('parent')->withCount('products')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->when($request->filled('parent_id'), fn ($q) => $request->parent_id === 'root' ? $q->whereNull('parent_id') : $q->where('parent_id', $request->parent_id))
            ->orderByRaw('COALESCE(parent_id, id)')->orderByRaw('parent_id IS NOT NULL')->orderBy('sort_order')
            ->paginate($this->perPage(50, 200));

        return $this->paginated($page, CategoryResource::class, 'Categories retrieved successfully');
    }

    public function show(Category $category): JsonResponse
    {
        return $this->ok(new CategoryResource($category->load(['parent', 'children'])->loadCount('products')), 'Category retrieved successfully');
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $data = Arr::except($request->validated(), ['image', 'remove_image']);
        $data['slug'] = $data['slug'] ?? $this->slugs->unique(Category::class, $data['name']);
        if ($request->hasFile('image')) {
            $data['image_path'] = $this->uploads->store($request->file('image'), 'categories');
        }
        $category = Category::create($data);
        $this->audit->log('category.created', $category, null, $data);

        return $this->created(new CategoryResource($category->load('parent')), 'Category created successfully');
    }

    /** Accepts PUT (JSON) or POST with _method=PUT (multipart, when uploading an image). */
    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $data = Arr::except($request->validated(), ['image', 'remove_image']);
        if (array_key_exists('slug', $data) && ! $data['slug']) {
            unset($data['slug']);
        }
        if ($this->createsCycle($category, $data['parent_id'] ?? $category->parent_id)) {
            return response()->json(['success' => false, 'message' => 'A category cannot be moved under one of its own sub-categories.', 'errors' => ['parent_id' => ['Invalid parent.']]], 422);
        }
        if ($request->hasFile('image')) {
            $this->uploads->delete($category->image_path);
            $data['image_path'] = $this->uploads->store($request->file('image'), 'categories');
        } elseif ($request->boolean('remove_image')) {
            $this->uploads->delete($category->image_path);
            $data['image_path'] = null;
        }
        $category->update($data);
        $this->audit->changes('category.updated', $category);

        return $this->ok(new CategoryResource($category->fresh()->load('parent')), 'Category updated successfully');
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return response()->json(['success' => false, 'message' => 'This category still has products. Move or delete them first.'], 422);
        }
        if ($category->children()->exists()) {
            return response()->json(['success' => false, 'message' => 'This category has sub-categories. Delete or move them first.'], 422);
        }
        $category->delete();
        $this->audit->log('category.deleted', $category);

        return $this->deleted('Category deleted');
    }

    private function createsCycle(Category $category, ?int $parentId): bool
    {
        while ($parentId) {
            if ($parentId === $category->id) {
                return true;
            }
            $parentId = Category::whereKey($parentId)->value('parent_id');
        }

        return false;
    }
}
