<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['is_active' => true, 'used_count' => 0, 'min_order_amount' => 0, 'value' => 0];

    protected $fillable = [
        'code', 'name', 'description', 'type', 'value', 'min_order_amount', 'max_discount',
        'starts_at', 'expires_at', 'usage_limit', 'per_user_limit', 'is_active',
    ];

    protected $casts = [
        'type' => CouponType::class,
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'used_count' => 'integer',
    ];

    protected function code(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(set: fn ($v) => strtoupper(trim((string) $v)));
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function scopeCurrentlyValid(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function hasRestrictions(): bool
    {
        return $this->products()->exists() || $this->categories()->exists();
    }
}
