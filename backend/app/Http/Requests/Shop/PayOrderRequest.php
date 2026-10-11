<?php

namespace App\Http\Requests\Shop;

use App\Enums\PaymentMethod;
use App\Http\Requests\ApiFormRequest;
use App\Services\Payments\PaymentManager;
use Illuminate\Validation\Rule;

class PayOrderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return array_merge([
            'payment_method' => ['required', Rule::in(PaymentMethod::onlineValues())],
            'payment_details' => [in_array($this->input('payment_method'), [PaymentMethod::Razorpay->value, PaymentMethod::Upi->value], true) ? 'nullable' : 'required', 'array'],
        ], app(PaymentManager::class)->rulesFor($this->input('payment_method')));
    }
}
