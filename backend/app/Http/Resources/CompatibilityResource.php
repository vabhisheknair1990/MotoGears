<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompatibilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'manufacturer' => $this->manufacturer ? ['id' => $this->manufacturer->id, 'name' => $this->manufacturer->name, 'slug' => $this->manufacturer->slug] : null,
            'model' => $this->model ? ['id' => $this->model->id, 'name' => $this->model->name] : null,
            'variant' => $this->variant ? [
                'id' => $this->variant->id, 'name' => $this->variant->name, 'fuel_type' => $this->variant->fuel_type,
                'transmission' => $this->variant->transmission, 'engine' => $this->variant->engine,
            ] : null,
            'notes' => $this->notes,
            'label' => $this->label(),
        ];
    }
}
