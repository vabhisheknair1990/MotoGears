<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => Media::url($this->logo_path),
            'description' => $this->description,
            'website' => $this->website,
            'country' => $this->country,
            'seo_title' => $this->seo_title ?: $this->name,
            'seo_description' => $this->seo_description ?: $this->description,
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'sort_order' => (int) $this->sort_order,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
