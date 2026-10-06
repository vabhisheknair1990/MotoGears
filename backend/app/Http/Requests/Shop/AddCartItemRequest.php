<?php

namespace App\Http\Requests\Shop;

use App\Http\Requests\ApiFormRequest;

class AddCartItemRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
