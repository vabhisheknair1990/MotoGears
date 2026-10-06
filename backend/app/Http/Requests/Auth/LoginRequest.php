<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

class LoginRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
