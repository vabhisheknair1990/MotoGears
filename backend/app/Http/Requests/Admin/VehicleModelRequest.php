<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class VehicleModelRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $model = $this->route('model');
        $req = $model ? 'sometimes' : 'required';
        $manufacturerId = $this->input('vehicle_manufacturer_id', $model?->vehicle_manufacturer_id);

        return [
            'vehicle_manufacturer_id' => [$req, 'integer', 'exists:vehicle_manufacturers,id'],
            'name' => [$req, 'string', 'max:100', Rule::unique('vehicle_models', 'name')->where('vehicle_manufacturer_id', $manufacturerId)->ignore($model?->id)],
            'vehicle_type' => [$req, Rule::in(['car', 'motorcycle'])],
            'body_type' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
