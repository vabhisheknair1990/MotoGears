<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogPostController extends Controller
{
    public function __construct(private SlugService $slugs, private ImageUploadService $uploads, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,published']]);
        $page = BlogPost::with(['category', 'author', 'tags'])
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->paginate($this->perPage(20));

        return $this->paginated($page, BlogPostResource::class, 'Blog posts retrieved successfully');
    }

    public function show(BlogPost $blog): JsonResponse
    {
        return $this->ok(new BlogPostResource($blog->load(['category', 'author', 'tags'])), 'Blog post retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $post = BlogPost::create($this->prepare($request, $data, null));
        $this->syncTags($post, $data['tags'] ?? []);
        $this->audit->log('blog_post.created', $post, null, Arr::except($data, ['cover_image', 'content']));

        return $this->created(new BlogPostResource($post->load(['category', 'author', 'tags'])), 'Blog post created successfully');
    }

    public function update(Request $request, BlogPost $blog): JsonResponse
    {
        $data = $this->validated($request, $blog);
        $blog->update($this->prepare($request, $data, $blog));
        if (array_key_exists('tags', $data)) {
            $this->syncTags($blog, $data['tags'] ?? []);
        }
        $this->audit->changes('blog_post.updated', $blog);

        return $this->ok(new BlogPostResource($blog->fresh()->load(['category', 'author', 'tags'])), 'Blog post updated successfully');
    }

    public function destroy(BlogPost $blog): JsonResponse
    {
        $blog->delete();
        $this->audit->log('blog_post.deleted', $blog);

        return $this->deleted('Blog post deleted');
    }

    private function validated(Request $request, ?BlogPost $post): array
    {
        if (is_string($request->input('tags'))) {
            $request->merge(['tags' => json_decode($request->input('tags'), true) ?? array_filter(array_map('trim', explode(',', $request->input('tags'))))]);
        }
        foreach (['blog_category_id', 'published_at'] as $n) {
            if (in_array($request->input($n), ['', 'null'], true)) {
                $request->merge([$n => null]);
            }
        }
        $req = $post ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$req, 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash', Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => [$req, 'string', 'max:200000'],
            'status' => [$req, Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array', 'max:15'],
            'tags.*' => ['string', 'max:40'],
            'cover_image' => ImageUploadService::rules(false, 8192),
        ]);
    }

    private function prepare(Request $request, array $data, ?BlogPost $post): array
    {
        $attrs = Arr::except($data, ['tags', 'cover_image']);
        if (! $post) {
            $attrs['author_id'] = $request->user()->id;
        }
        if (empty($attrs['slug']) && ! $post) {
            $attrs['slug'] = $this->slugs->unique(BlogPost::class, $data['title']);
        } elseif (array_key_exists('slug', $attrs) && empty($attrs['slug'])) {
            unset($attrs['slug']);
        }
        if (($attrs['status'] ?? $post?->status) === 'published' && empty($attrs['published_at']) && ! $post?->published_at) {
            $attrs['published_at'] = now();
        }
        if (isset($attrs['content'])) {
            $attrs['reading_minutes'] = max(1, (int) ceil(str_word_count(strip_tags($attrs['content'])) / 200));
            $attrs['excerpt'] = $attrs['excerpt'] ?? Str::limit(strip_tags($attrs['content']), 180);
        }
        if ($request->hasFile('cover_image')) {
            if ($post) {
                $this->uploads->delete($post->cover_image_path);
            }
            $attrs['cover_image_path'] = $this->uploads->store($request->file('cover_image'), 'blog');
        }

        return $attrs;
    }

    private function syncTags(BlogPost $post, array $names): void
    {
        $ids = collect($names)->map(fn ($n) => trim($n))->filter()->unique()->map(
            fn ($n) => BlogTag::firstOrCreate(['slug' => Str::slug($n)], ['name' => $n])->id
        );
        $post->tags()->sync($ids);
    }
}
