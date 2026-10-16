<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class VehicleVariantRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $req = $this->route('variant') ? 'sometimes' : 'required';

        return [
            'vehicle_model_id' => [$req, 'integer', 'exists:vehicle_models,id'],
            'name' => [$req, 'string', 'max:120'],
            'engine' => ['nullable', 'string', 'max:100'],
            'fuel_type' => ['nullable', Rule::in(['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid', 'LPG'])],
            'transmission' => ['nullable', Rule::in(['MT', 'AT', 'AMT', 'CVT', 'DCT', 'IVT', 'Single-speed'])],
            'displacement_cc' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
