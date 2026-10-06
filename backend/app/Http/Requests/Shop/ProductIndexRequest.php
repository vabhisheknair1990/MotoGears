<?php

namespace App\Http\Requests\Shop;

use App\Http\Requests\ApiFormRequest;
use App\Services\ProductQueryService;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable'],
            'brand' => ['sometimes', 'nullable'],
            'vehicle_variant' => ['sometimes', 'nullable', 'integer'],
            'vehicle_variant_id' => ['sometimes', 'nullable', 'integer'],
            'vehicle_year' => ['sometimes', 'nullable', 'integer'],
            'vehicle_model' => ['sometimes', 'nullable', 'integer'],
            'vehicle_manufacturer' => ['sometimes', 'nullable', 'integer'],
            'vehicle_type' => ['sometimes', 'nullable', Rule::in(['car', 'motorcycle', 'universal'])],
            'min_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rating' => ['sometimes', 'nullable', 'numeric', 'between:0,5'],
            'discount' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'in_stock' => ['sometimes', 'nullable'],
            'featured' => ['sometimes', 'nullable'],
            'attributes' => ['sometimes', 'nullable', 'array'],
            'sort' => ['sometimes', 'nullable', Rule::in(array_keys(ProductQueryService::SORTS))],
            'ids' => ['sometimes', 'nullable'],
        ];
    }

    /** Normalised filter array (accepts spec aliases like vehicle_variant_id and q). */
    public function filters(): array
    {
        $f = $this->validated();
        $f['search'] = $f['search'] ?? $f['q'] ?? null;
        $f['vehicle_variant'] = $f['vehicle_variant'] ?? $f['vehicle_variant_id'] ?? null;
        unset($f['q'], $f['vehicle_variant_id'], $f['page'], $f['per_page']);

        return $f;
    }
}
