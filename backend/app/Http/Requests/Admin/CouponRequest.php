<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->code))]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('coupon')?->id;
        $req = $id ? 'sometimes' : 'required';

        return [
            'code' => [$req, 'string', 'max:40', 'regex:/^[A-Z0-9_\-]+$/', Rule::unique('coupons', 'code')->ignore($id)],
            'name' => [$req, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => [$req, Rule::in(['percentage', 'fixed', 'free_shipping'])],
            'value' => ['nullable', 'numeric', 'min:0', 'max:1000000', Rule::when($this->type === 'percentage', ['max:100'])],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }

    public function after(): array
    {
        return [function ($v) {
            if (in_array($this->type, ['percentage', 'fixed'], true) && (float) $this->value <= 0) {
                $v->errors()->add('value', 'A discount value greater than zero is required.');
            }
        }];
    }
}
