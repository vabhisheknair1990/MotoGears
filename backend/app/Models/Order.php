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
        'order_number', 'user_id', 'customer_name', 'customer_email', 'customer_phone', 'is_guest', 'guest_token', 'status', 'payment_status', 'payment_method', 'shipping_method',
        'subtotal', 'discount', 'shipping_amount', 'tax', 'grand_total', 'currency', 'coupon_id', 'coupon_code',
        'billing_address', 'shipping_address', 'tracking_number', 'carrier', 'customer_notes', 'admin_notes',
        'ip_address', 'placed_at', 'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    /** The guest access token is a secret: never serialise it. */
    protected $hidden = ['guest_token'];

    protected $casts = [
        'is_guest' => 'boolean',
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

    /** True when $token is this guest order's access token (constant-time comparison). */
    public function matchesGuestToken(?string $token): bool
    {
        return $this->guest_token !== null && is_string($token) && $token !== '' && hash_equals($this->guest_token, $token);
    }

    /** Where order emails go: the account if there is one, otherwise the guest's email. */
    public function notifiable(): ?object
    {
        if ($this->user && ! $this->user->trashed()) {
            return $this->user;
        }

        return $this->customer_email
            ? \Illuminate\Support\Facades\Notification::route('mail', [$this->customer_email => $this->customer_name ?: $this->customer_email])
            : null;
    }

    /** Storefront page for this order: the account page, or the guest's secret link. */
    public function customerUrl(): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $this->is_guest && $this->guest_token
            ? $base.'/order/'.$this->order_number.'?token='.$this->guest_token
            : $base.'/account/orders/'.$this->id;
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
