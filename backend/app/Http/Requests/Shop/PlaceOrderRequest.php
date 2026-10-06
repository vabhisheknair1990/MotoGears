<?php

namespace App\Http\Requests\Shop;

use App\Enums\PaymentMethod;
use App\Http\Requests\Account\AddressRequest;
use App\Http\Requests\ApiFormRequest;
use App\Services\Payments\PaymentManager;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $rules = [
            'shipping_address_id' => ['nullable', 'required_without:shipping_address', 'integer'],
            'shipping_address' => ['nullable', 'array'],
            'billing_same_as_shipping' => ['sometimes', 'boolean'],
            'billing_address_id' => ['nullable', 'integer'],
            'billing_address' => ['nullable', 'array'],
            'save_address' => ['sometimes', 'boolean'],
            'shipping_method' => ['required', Rule::in(['standard', 'express'])],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'payment_details' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
        if ($this->filled('shipping_address')) {
            $rules = array_merge($rules, AddressRequest::fieldRules('shipping_address.'));
        }
        if ($this->filled('billing_address') && ! $this->boolean('billing_same_as_shipping')) {
            $rules = array_merge($rules, AddressRequest::fieldRules('billing_address.'));
        }

        return array_merge($rules, app(PaymentManager::class)->rulesFor($this->input('payment_method')));
    }

    public function attributes(): array
    {
        return [
            'payment_details.card_number' => 'card number',
            'payment_details.card_name' => 'name on card',
            'payment_details.expiry' => 'expiry date',
            'payment_details.cvv' => 'CVV',
            'payment_details.upi_id' => 'UPI ID',
        ];
    }
}
