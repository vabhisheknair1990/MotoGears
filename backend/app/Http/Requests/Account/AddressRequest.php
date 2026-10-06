<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;

class AddressRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->strip('name', 'line1', 'line2', 'landmark', 'city', 'state', 'label');
    }

    public static function fieldRules(string $prefix = '', bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            $prefix.'label' => ['nullable', 'string', 'max:30'],
            $prefix.'name' => [$req, 'string', 'max:100'],
            $prefix.'phone' => [$req, 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            $prefix.'line1' => [$req, 'string', 'max:190'],
            $prefix.'line2' => ['nullable', 'string', 'max:190'],
            $prefix.'landmark' => ['nullable', 'string', 'max:190'],
            $prefix.'city' => [$req, 'string', 'max:100'],
            $prefix.'state' => [$req, 'string', 'max:100'],
            $prefix.'postal_code' => [$req, 'string', 'regex:/^[1-9][0-9]{5}$/'],
            $prefix.'country' => ['nullable', 'string', 'size:2'],
        ];
    }

    public function rules(): array
    {
        return array_merge(self::fieldRules('', $this->isMethod('patch')), ['is_default' => ['sometimes', 'boolean']]);
    }

    public function messages(): array
    {
        return ['postal_code.regex' => 'Enter a valid 6-digit PIN code.', 'phone.regex' => 'Enter a valid phone number.'];
    }
}
