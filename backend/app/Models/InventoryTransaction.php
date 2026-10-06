<?php

namespace App\Models;

use App\Enums\InventoryTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'inventory_id', 'product_id', 'type', 'quantity', 'quantity_after', 'reserved_after',
        'reference_type', 'reference_id', 'reference_label', 'note', 'user_id',
    ];

    protected $casts = ['type' => InventoryTransactionType::class, 'created_at' => 'datetime'];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
