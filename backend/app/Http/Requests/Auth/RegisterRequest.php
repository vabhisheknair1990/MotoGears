<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->email))]);
        $this->strip('name');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'An account with this email already exists. Try logging in instead.'];
    }
}
