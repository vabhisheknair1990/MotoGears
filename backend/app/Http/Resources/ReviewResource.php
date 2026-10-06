<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->is('v1/admin/*');
        $author = $this->user?->name ?? 'Customer';

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'status' => $this->status->value,
            'is_verified_purchase' => (bool) $this->is_verified_purchase,
            'is_featured' => (bool) $this->is_featured,
            'helpful_count' => $this->helpful_count,
            // Public listing shows first name + initial only.
            'author' => $isAdmin ? $author : Str::of($author)->explode(' ')->pipe(fn ($p) => $p[0].(isset($p[1]) ? ' '.mb_substr($p[1], 0, 1).'.' : '')),
            'customer' => $this->when($isAdmin, fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email] : null),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($i) => ['id' => $i->id, 'url' => Media::url($i->path)])->values()),
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id, 'name' => $this->product->name, 'slug' => $this->product->slug,
                'image' => Media::url($this->product->primaryImage?->path),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
