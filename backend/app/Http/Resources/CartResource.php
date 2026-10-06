<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Wraps the array produced by CartService::summary(). */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->resource;

        return [
            'id' => $s['id'],
            'token' => $s['token'],
            'items' => collect($s['items'])->map(fn (array $i) => [
                'id' => $i['id'],
                'product' => (new ProductCardResource($i['product']))->toArray($request),
                'quantity' => $i['quantity'],
                'unit_price' => $i['unit_price'],
                'mrp' => $i['mrp'],
                'line_subtotal' => $i['line_subtotal'],
                'discount' => $i['discount'],
                'tax_rate' => $i['tax_rate'],
                'tax_amount' => $i['tax_amount'],
                'line_total' => $i['line_total'],
                'available_stock' => $i['available_stock'],
                'max_quantity' => $i['max_quantity'],
                'in_stock' => $i['in_stock'],
            ])->values(),
            'item_count' => $s['item_count'],
            'total_quantity' => $s['total_quantity'],
            'subtotal' => $s['subtotal'],
            'discount' => $s['discount'],
            'shipping' => $s['shipping'],
            'tax' => $s['tax'],
            'grand_total' => $s['grand_total'],
            'mrp_total' => $s['mrp_total'],
            'savings' => $s['savings'],
            'coupon' => $s['coupon'],
            'shipping_method' => $s['shipping_method'],
            'free_shipping_threshold' => $s['free_shipping_threshold'],
            'amount_to_free_shipping' => $s['amount_to_free_shipping'],
            'warnings' => $s['warnings'],
            'is_checkout_ready' => $s['is_checkout_ready'],
        ];
    }
}
