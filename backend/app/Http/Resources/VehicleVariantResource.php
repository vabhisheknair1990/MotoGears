<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_model_id' => $this->vehicle_model_id,
            'name' => $this->name,
            'year_from' => $this->year_from,
            'year_to' => $this->year_to,
            'year_range' => $this->yearRange(),
            'engine' => $this->engine,
            'fuel_type' => $this->fuel_type,
            'transmission' => $this->transmission,
            'displacement_cc' => $this->displacement_cc,
            'is_active' => (bool) $this->is_active,
            'model' => $this->whenLoaded('model', fn () => [
                'id' => $this->model->id,
                'name' => $this->model->name,
                'vehicle_type' => $this->model->vehicle_type,
                'manufacturer' => $this->model->relationLoaded('manufacturer') ? [
                    'id' => $this->model->manufacturer->id, 'name' => $this->model->manufacturer->name, 'slug' => $this->model->manufacturer->slug,
                ] : null,
            ]),
            'full_name' => $this->when($this->relationLoaded('model') && $this->model->relationLoaded('manufacturer'), fn () => $this->fullName()),
        ];
    }
}
