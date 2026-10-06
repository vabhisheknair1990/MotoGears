<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

class ForgotPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:190']];
    }
}
