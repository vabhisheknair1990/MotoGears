<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => Media::url($this->image_path),
            'icon' => $this->icon,
            'vehicle_type' => $this->vehicle_type,
            'seo_title' => $this->seo_title ?: $this->name,
            'seo_description' => $this->seo_description ?: $this->description,
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'sort_order' => (int) $this->sort_order,
            'products_count' => $this->whenCounted('products'),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? ['id' => $this->parent->id, 'name' => $this->parent->name, 'slug' => $this->parent->slug] : null),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
