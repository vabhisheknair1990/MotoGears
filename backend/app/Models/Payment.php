<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'method', 'gateway', 'gateway_order_id', 'transaction_id', 'amount', 'currency', 'status', 'failure_reason', 'meta', 'paid_at',
    ];

    protected $casts = ['status' => PaymentStatus::class, 'amount' => 'decimal:2', 'meta' => 'array', 'paid_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
