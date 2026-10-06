<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleManufacturerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo' => $this->logoUrl(),
            'vehicle_type' => $this->vehicle_type,
            'country' => $this->country,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'models_count' => $this->whenCounted('models'),
            'models' => VehicleModelResource::collection($this->whenLoaded('models')),
        ];
    }
}
