<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'name', 'options', 'price_adjustment', 'is_active', 'sort_order'];

    protected $casts = ['options' => 'array', 'price_adjustment' => 'decimal:2', 'is_active' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
