<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class OrderStatusRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(OrderStatus::values())],
            'comment' => ['nullable', 'string', 'max:500'],
            'tracking_number' => ['nullable', 'string', 'max:60'],
            'carrier' => ['nullable', 'string', 'max:60'],
            'notify_customer' => ['sometimes', 'boolean'],
        ];
    }
}
