<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->email))]);
        }
        $this->strip('name');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ];
    }
}
