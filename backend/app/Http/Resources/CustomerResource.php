<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Admin view of a customer. */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'marketing_opt_in' => (bool) $this->marketing_opt_in,
            'orders_count' => (int) ($this->orders_count ?? 0),
            'total_spent' => round((float) ($this->total_spent ?? 0), 2),
            'last_order_at' => $this->last_order_at ? \Carbon\Carbon::parse($this->last_order_at)->toIso8601String() : null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'vehicles' => CustomerVehicleResource::collection($this->whenLoaded('vehicles')),
        ];
    }
}
