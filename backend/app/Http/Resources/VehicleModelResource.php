<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_manufacturer_id' => $this->vehicle_manufacturer_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'vehicle_type' => $this->vehicle_type,
            'body_type' => $this->body_type,
            'image' => Media::url($this->image_path),
            'is_active' => (bool) $this->is_active,
            'variants_count' => $this->whenCounted('variants'),
            'manufacturer' => $this->whenLoaded('manufacturer', fn () => ['id' => $this->manufacturer->id, 'name' => $this->manufacturer->name, 'slug' => $this->manufacturer->slug]),
            'variants' => VehicleVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
