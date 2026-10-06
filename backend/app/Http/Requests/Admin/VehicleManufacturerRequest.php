<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Services\ImageUploadService;
use Illuminate\Validation\Rule;

class VehicleManufacturerRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('manufacturer')?->id;
        $req = $id ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:100', Rule::unique('vehicle_manufacturers', 'name')->ignore($id)],
            'vehicle_type' => [$req, Rule::in(['car', 'motorcycle', 'both'])],
            'country' => ['nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'logo' => ImageUploadService::rules(),
        ];
    }
}
