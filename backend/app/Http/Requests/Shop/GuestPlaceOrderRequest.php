<?php

namespace App\Http\Requests\Shop;

use App\Http\Requests\Account\AddressRequest;

/**
 * Guest checkout: same choices as a customer order, plus contact details. The address is
 * always typed in (guests have no address book) and the email receives the order link.
 */
class GuestPlaceOrderRequest extends PlaceOrderRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['shipping_address_id'], $rules['billing_address_id'], $rules['save_address']);
        $rules['email'] = ['required', 'email:rfc', 'max:190'];
        $rules['phone'] = ['nullable', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'];
        $rules['shipping_address'] = ['required', 'array'];

        return array_merge($rules, AddressRequest::fieldRules('shipping_address.'));
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Enter your email — we send your order confirmation and tracking link there.',
            'shipping_address.required' => 'Enter a delivery address.',
            'shipping_address.postal_code.regex' => 'Enter a valid 6-digit PIN code.',
            'shipping_address.phone.regex' => 'Enter a valid phone number.',
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'shipping_address.name' => 'full name', 'shipping_address.phone' => 'phone number', 'shipping_address.line1' => 'address',
            'shipping_address.city' => 'city', 'shipping_address.state' => 'state', 'shipping_address.postal_code' => 'PIN code',
        ];
    }
}
