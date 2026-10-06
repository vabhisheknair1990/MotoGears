<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return ['current_password.current_password' => 'Your current password is incorrect.'];
    }
}
