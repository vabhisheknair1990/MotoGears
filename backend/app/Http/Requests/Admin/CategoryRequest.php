<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Services\ImageUploadService;
use Illuminate\Validation\Rule;

class CategoryRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->strip('name', 'seo_title');
        foreach (['is_active', 'is_featured', 'remove_image'] as $b) {
            if ($this->has($b)) {
                $this->merge([$b => filter_var($this->input($b), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
        if ($this->input('parent_id') === '' || $this->input('parent_id') === 'null') {
            $this->merge(['parent_id' => null]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;
        $req = $this->isMethod('post') && ! $id ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at'), Rule::notIn(array_filter([$id]))],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:60'],
            'vehicle_type' => ['nullable', Rule::in(['car', 'motorcycle', 'universal'])],
            'seo_title' => ['nullable', 'string', 'max:190'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image' => ImageUploadService::rules(),
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }
}
