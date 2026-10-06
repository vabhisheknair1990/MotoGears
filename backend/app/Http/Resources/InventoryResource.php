<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'image' => Media::url($this->product->primaryImage?->path),
                'brand' => $this->product->brand?->name,
                'is_active' => (bool) $this->product->is_active,
            ] : null),
            'quantity' => $this->quantity,
            'reserved' => $this->reserved,
            'available' => $this->available(),
            'low_stock_threshold' => $this->low_stock_threshold,
            'allow_backorder' => (bool) $this->allow_backorder,
            'location' => $this->location,
            'status' => $this->status(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
