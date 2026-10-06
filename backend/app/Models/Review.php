<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['status' => 'pending', 'is_verified_purchase' => false, 'is_featured' => false, 'helpful_count' => 0];

    protected $fillable = ['product_id', 'user_id', 'order_id', 'rating', 'title', 'comment', 'status', 'is_verified_purchase', 'is_featured'];

    protected $casts = [
        'status' => ReviewStatus::class,
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function booted(): void
    {
        $refresh = fn (Review $r) => $r->product?->refreshRating();
        static::saved($refresh);
        static::deleted($refresh);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', ReviewStatus::Approved->value);
    }
}
