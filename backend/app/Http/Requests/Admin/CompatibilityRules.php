<?php

namespace App\Http\Requests\Admin;

class CompatibilityRules
{
    public static function rules(string $prefix = ''): array
    {
        return [
            $prefix.'vehicle_manufacturer_id' => ['required', 'integer', 'exists:vehicle_manufacturers,id'],
            $prefix.'vehicle_model_id' => ['nullable', 'integer', 'exists:vehicle_models,id'],
            $prefix.'vehicle_variant_id' => ['nullable', 'integer', 'exists:vehicle_variants,id'],
            $prefix.'year_from' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            $prefix.'year_to' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            $prefix.'notes' => ['nullable', 'string', 'max:190'],
        ];
    }
}
