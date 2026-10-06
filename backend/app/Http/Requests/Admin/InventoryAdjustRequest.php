<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class InventoryAdjustRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['stock_received', 'manual_adjustment', 'return'])],
            'quantity' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
