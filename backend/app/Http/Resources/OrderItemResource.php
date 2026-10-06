<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_slug' => $this->product_slug,
            'sku' => $this->sku,
            'part_number' => $this->part_number,
            'brand_name' => $this->brand_name,
            'image' => Media::url($this->image_path),
            'mrp' => (float) $this->mrp,
            'unit_price' => (float) $this->unit_price,
            'quantity' => $this->quantity,
            'discount' => (float) $this->discount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'line_total' => (float) $this->line_total,
            'reviewed' => $this->when(isset($this->reviewed), fn () => (bool) $this->reviewed),
        ];
    }
}
