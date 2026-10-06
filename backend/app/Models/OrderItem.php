<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_name', 'product_slug', 'sku', 'part_number', 'brand_name', 'image_path',
        'mrp', 'unit_price', 'quantity', 'discount', 'tax_rate', 'tax_amount', 'line_total',
    ];

    protected $casts = [
        'mrp' => 'decimal:2', 'unit_price' => 'decimal:2', 'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2', 'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2', 'quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
