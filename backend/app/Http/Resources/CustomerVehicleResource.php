<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerVehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'nickname' => $this->nickname,
            'registration_number' => $this->registration_number,
            'is_default' => (bool) $this->is_default,
            'variant' => new VehicleVariantResource($this->whenLoaded('variant')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
