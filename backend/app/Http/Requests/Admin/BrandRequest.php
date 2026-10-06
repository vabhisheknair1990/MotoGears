<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Services\ImageUploadService;
use Illuminate\Validation\Rule;

class BrandRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->strip('name', 'seo_title');
        foreach (['is_active', 'is_featured', 'remove_logo'] as $b) {
            if ($this->has($b)) {
                $this->merge([$b => filter_var($this->input($b), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function rules(): array
    {
        $id = $this->route('brand')?->id;
        $req = $id ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:120', Rule::unique('brands', 'name')->ignore($id)->whereNull('deleted_at')],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('brands', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'country' => ['nullable', 'string', 'max:60'],
            'seo_title' => ['nullable', 'string', 'max:190'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'logo' => ImageUploadService::rules(),
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }
}
