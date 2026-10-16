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
            $prefix.'notes' => ['nullable', 'string', 'max:190'],
        ];
    }
}
