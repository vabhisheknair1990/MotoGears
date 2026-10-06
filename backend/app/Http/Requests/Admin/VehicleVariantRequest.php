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
            'year_from' => [$req, 'integer', 'min:1950', 'max:'.(date('Y') + 2)],
            'year_to' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 2)],
            'engine' => ['nullable', 'string', 'max:100'],
            'fuel_type' => ['nullable', Rule::in(['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid', 'LPG'])],
            'transmission' => ['nullable', Rule::in(['MT', 'AT', 'AMT', 'CVT', 'DCT', 'IVT', 'Single-speed'])],
            'displacement_cc' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $variant = $this->route('variant');
            $from = $this->input('year_from', $variant?->year_from);
            $to = $this->has('year_to') ? $this->input('year_to') : $variant?->year_to;
            if ($from && $to && (int) $to < (int) $from) {
                $validator->errors()->add('year_to', 'The end year must be the same as or after the start year.');
            }
        }];
    }
}
