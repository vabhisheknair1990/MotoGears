<?php

namespace App\Http\Requests\Shop;

use App\Http\Requests\ApiFormRequest;

class ContactRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->strip('name', 'subject', 'message');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['prohibited'], // honeypot
        ];
    }
}
