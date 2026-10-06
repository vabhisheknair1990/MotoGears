<?php

namespace App\Http\Requests\Shop;

use App\Http\Requests\ApiFormRequest;

class UpdateCartItemRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['quantity' => ['required', 'integer', 'min:0', 'max:100']];
    }
}
