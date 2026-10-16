<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;

class CustomerVehicleRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'vehicle_variant_id' => ['required', 'integer', 'exists:vehicle_variants,id'],
            'nickname' => ['nullable', 'string', 'max:60'],
            'registration_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
