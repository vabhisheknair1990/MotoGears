<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        // Multipart/form-data sends JSON structures as strings — decode them.
        foreach (['whats_included', 'specifications', 'tags', 'attribute_value_ids', 'faqs', 'variants', 'compatibilities'] as $k) {
            if (is_string($this->input($k))) {
                $this->merge([$k => json_decode($this->input($k), true) ?? []]);
            }
        }
        if ($this->has('sku')) {
            $this->merge(['sku' => strtoupper(trim((string) $this->sku))]);
        }
        $this->strip('name', 'short_description');
    }

    public function rules(): array
    {
        return self::fieldRules($this->route('product')?->id, $this->isMethod('post'));
    }

    /**
     * Shared by the product form and the Excel import so both enforce exactly the same rules.
     * $creating = true makes the core fields required; otherwise they are "sometimes".
     */
    public static function fieldRules(?int $id, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash', Rule::unique('products', 'slug')->ignore($id)],
            'sku' => [$req, 'string', 'max:64', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('products', 'sku')->ignore($id)],
            'part_number' => ['nullable', 'string', 'max:100'],
            'category_id' => [$req, 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id' => [$req, 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'mrp' => [$req, 'numeric', 'min:0', 'max:9999999'],
            'price' => [$req, 'numeric', 'min:0', 'max:9999999'],
            'tax_rate' => ['nullable', Rule::in([0, 5, 12, 18, 28, '0', '5', '12', '18', '28'])],
            'vehicle_type' => [$req, Rule::in(['car', 'motorcycle', 'universal'])],
            'is_universal' => ['sometimes', 'boolean'],
            'position' => ['nullable', 'string', 'max:60'],
            'material' => ['nullable', 'string', 'max:100'],
            'dimensions' => ['nullable', 'string', 'max:100'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'warranty' => ['nullable', 'string', 'max:150'],
            'installation_info' => ['nullable', 'string', 'max:5000'],
            'whats_included' => ['nullable', 'array', 'max:30'],
            'whats_included.*' => ['string', 'max:190'],
            'specifications' => ['nullable', 'array', 'max:50'],
            'specifications.*.label' => ['required', 'string', 'max:100'],
            'specifications.*.value' => ['required', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:50'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['integer', 'exists:attribute_values,id'],
            'faqs' => ['nullable', 'array', 'max:20'],
            'faqs.*.question' => ['required', 'string', 'max:255'],
            'faqs.*.answer' => ['required', 'string', 'max:2000'],
            'variants' => ['nullable', 'array', 'max:30'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required', 'string', 'max:64'],
            'variants.*.name' => ['required', 'string', 'max:190'],
            'variants.*.options' => ['nullable', 'array'],
            'variants.*.price_adjustment' => ['nullable', 'numeric'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'compatibilities' => ['nullable', 'array', 'max:200'],
            ...CompatibilityRules::rules('compatibilities.*.'),
            // Inventory (create only; later changes go through the inventory module)
            'initial_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'allow_backorder' => ['sometimes', 'boolean'],
        ];
    }

    public static function priceAboveMrp(mixed $mrp, mixed $price): bool
    {
        return is_numeric($mrp) && is_numeric($price) && (float) $price > (float) $mrp;
    }

    public function after(): array
    {
        return [function ($validator) {
            $product = $this->route('product');
            $mrp = $this->input('mrp', $product?->mrp);
            $price = $this->input('price', $product?->price);
            if (self::priceAboveMrp($mrp, $price)) {
                $validator->errors()->add('price', 'Selling price cannot be higher than MRP.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'sku.regex' => 'SKU may only contain letters, numbers, dashes and underscores.',
        ];
    }
}
