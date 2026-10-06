<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity' => $this->quantity,
            'quantity_after' => $this->quantity_after,
            'reserved_after' => $this->reserved_after,
            'reference' => $this->reference_label,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'note' => $this->note,
            'product' => $this->whenLoaded('product', fn () => $this->product ? ['id' => $this->product->id, 'name' => $this->product->name, 'sku' => $this->product->sku] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
