<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Lightweight product representation for grids, carts and wishlists. */
class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $inv = $this->relationLoaded('inventory') ? $this->inventory : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'part_number' => $this->part_number,
            'short_description' => $this->short_description,
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? ['id' => $this->brand->id, 'name' => $this->brand->name, 'slug' => $this->brand->slug] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug] : null),
            'image' => Media::url($this->relationLoaded('primaryImage') ? $this->primaryImage?->path : null),
            'mrp' => (float) $this->mrp,
            'price' => (float) $this->price,
            'discount_percent' => $this->discountPercent(),
            'rating' => (float) $this->rating_avg,
            'review_count' => (int) $this->rating_count,
            'vehicle_type' => $this->vehicle_type,
            'is_universal' => (bool) $this->is_universal,
            'is_featured' => (bool) $this->is_featured,
            'stock_status' => $inv?->status() ?? 'out_of_stock',
            'available_stock' => $inv?->available() ?? 0,
            'fits_vehicle' => $this->when(isset($this->fits_vehicle), fn () => (bool) $this->fits_vehicle),
        ];
    }
}
