<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;
use App\Models\VehicleVariant;

class CustomerVehicleRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'vehicle_variant_id' => ['required', 'integer', 'exists:vehicle_variants,id'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'nickname' => ['nullable', 'string', 'max:60'],
            'registration_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if (! $this->year || ! $this->vehicle_variant_id) {
                return;
            }
            $variant = VehicleVariant::find($this->vehicle_variant_id);
            if ($variant && ($this->year < $variant->year_from || ($variant->year_to && $this->year > $variant->year_to))) {
                $validator->errors()->add('year', "This variant was sold from {$variant->yearRange()}.");
            }
        }];
    }
}
