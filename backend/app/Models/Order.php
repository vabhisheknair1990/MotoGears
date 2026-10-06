<?php

namespace App\Models;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number', 'user_id', 'status', 'payment_status', 'payment_method', 'shipping_method',
        'subtotal', 'discount', 'shipping_amount', 'tax', 'grand_total', 'currency', 'coupon_id', 'coupon_code',
        'billing_address', 'shipping_address', 'tracking_number', 'carrier', 'customer_notes', 'admin_notes',
        'ip_address', 'placed_at', 'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'payment_status' => OrderPaymentStatus::class,
        'payment_method' => PaymentMethod::class,
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'placed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class)->withTrashed();
    }

    public function scopeRevenue(Builder $q): Builder
    {
        return $q->whereIn('status', array_map(fn ($s) => $s->value, OrderStatus::revenueStatuses()));
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
