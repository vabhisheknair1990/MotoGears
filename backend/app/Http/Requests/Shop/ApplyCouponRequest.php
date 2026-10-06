<?php

namespace App\Http\Requests\Shop;

use App\Http\Requests\ApiFormRequest;

class ApplyCouponRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/']];
    }
}
