<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $full = $request->routeIs('*.blog.show') || $request->is('v1/admin/*');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->when($full, $this->content),
            'cover_image' => Media::url($this->cover_image_path),
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'reading_minutes' => $this->reading_minutes,
            'meta_title' => $this->meta_title ?: $this->title,
            'meta_description' => $this->meta_description ?: $this->excerpt,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug] : null),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])),
            'blog_category_id' => $this->blog_category_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
