<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'value' => (float) $this->value,
            'min_order_amount' => (float) $this->min_order_amount,
            'max_discount' => $this->max_discount !== null ? (float) $this->max_discount : null,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'usage_limit' => $this->usage_limit,
            'per_user_limit' => $this->per_user_limit,
            'used_count' => $this->used_count,
            'is_active' => (bool) $this->is_active,
            'is_expired' => (bool) ($this->expires_at && $this->expires_at->isPast()),
            'product_ids' => $this->whenLoaded('products', fn () => $this->products->pluck('id')),
            'category_ids' => $this->whenLoaded('categories', fn () => $this->categories->pluck('id')),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
