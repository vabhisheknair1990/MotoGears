<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Product detail for the admin editor: includes cost price, inventory and relation IDs. */
class AdminProductResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'cost_price' => (float) $this->cost_price,
            'is_active' => (bool) $this->is_active,
            'sold_count' => (int) $this->sold_count,
            'view_count' => (int) $this->view_count,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'attribute_value_ids' => $this->whenLoaded('attributeValues', fn () => $this->attributeValues->pluck('id')),
            'inventory' => $this->whenLoaded('inventory', fn () => $this->inventory ? (new InventoryResource($this->inventory))->toArray($request) : null),
            'faqs' => $this->whenLoaded('faqs', fn () => $this->faqs->map(fn ($f) => ['id' => $f->id, 'question' => $f->question, 'answer' => $f->answer])->values()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($v) => [
                'id' => $v->id, 'sku' => $v->sku, 'name' => $v->name, 'options' => $v->options,
                'price_adjustment' => (float) $v->price_adjustment, 'is_active' => (bool) $v->is_active,
            ])->values()),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ]);
    }
}
