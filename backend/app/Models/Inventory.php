<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $attributes = ['quantity' => 0, 'reserved' => 0, 'low_stock_threshold' => 5, 'allow_backorder' => false];

    protected $fillable = ['product_id', 'sku', 'quantity', 'reserved', 'low_stock_threshold', 'allow_backorder', 'location'];

    protected $casts = ['quantity' => 'integer', 'reserved' => 'integer', 'low_stock_threshold' => 'integer', 'allow_backorder' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class)->latest('id');
    }

    public function available(): int
    {
        return max(0, $this->quantity - $this->reserved);
    }

    public function canFulfil(int $qty): bool
    {
        return $this->allow_backorder || $this->available() >= $qty;
    }

    public function status(): string
    {
        $available = $this->available();
        if ($available <= 0) {
            return $this->allow_backorder ? 'backorder' : 'out_of_stock';
        }

        return $available <= $this->low_stock_threshold ? 'low_stock' : 'in_stock';
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->whereRaw('(quantity - reserved) > 0')->whereRaw('(quantity - reserved) <= low_stock_threshold');
    }

    public function scopeOutOfStock(Builder $q): Builder
    {
        return $q->whereRaw('(quantity - reserved) <= 0');
    }
}
