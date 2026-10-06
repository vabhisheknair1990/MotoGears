<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffUserRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $id = $this->route('user')?->id;
        $req = $id ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:100'],
            'email' => [$req, 'email', 'max:190', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$id ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'roles' => [$req, 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('is_staff', true)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
