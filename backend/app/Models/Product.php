<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use FlushesStorefrontCache, HasFactory, SoftDeletes;

    protected $attributes = ['is_active' => true, 'is_featured' => false, 'is_universal' => false, 'rating_avg' => 0, 'rating_count' => 0, 'sold_count' => 0, 'view_count' => 0, 'tax_rate' => 18];

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'sku', 'part_number', 'short_description', 'description',
        'cost_price', 'mrp', 'price', 'tax_rate', 'vehicle_type', 'is_universal', 'position', 'material',
        'dimensions', 'weight_kg', 'warranty', 'installation_info', 'whats_included', 'specifications', 'tags',
        'video_url', 'meta_title', 'meta_description', 'is_active', 'is_featured',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'weight_kg' => 'decimal:3',
        'rating_avg' => 'decimal:2',
        'is_universal' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'whats_included' => 'array',
        'specifications' => 'array',
        'tags' => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────────
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany(['is_primary' => 'max', 'id' => 'min']);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ProductFaq::class)->orderBy('sort_order');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attributes')->with('attribute');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(ProductCompatibility::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('products.is_active', true);
    }

    /**
     * Products that fit the given vehicle variant. A compatibility row matches when it names the
     * variant exactly, or names the model/manufacturer with no narrower constraint and an
     * overlapping year range. Universal products always match.
     */
    public function scopeCompatibleWith(Builder $q, VehicleVariant $variant, ?int $year = null): Builder
    {
        $variant->loadMissing('model');
        $from = $year ?? $variant->year_from;
        $to = $year ?? ($variant->year_to ?? (int) date('Y'));

        $type = $variant->model->vehicle_type;

        return $q->where(function (Builder $outer) use ($variant, $from, $to, $type) {
            $outer->where(fn (Builder $u) => $u->where('products.is_universal', true)->whereIn('products.vehicle_type', [$type, 'universal']))
                ->orWhereHas('compatibilities', function (Builder $c) use ($variant, $from, $to) {
                    $c->where(function (Builder $w) use ($variant) {
                        $w->where('vehicle_variant_id', $variant->id)
                            ->orWhere(fn ($m) => $m->whereNull('vehicle_variant_id')->where('vehicle_model_id', $variant->vehicle_model_id))
                            ->orWhere(fn ($m) => $m->whereNull('vehicle_model_id')->where('vehicle_manufacturer_id', $variant->model->vehicle_manufacturer_id));
                    })
                        ->where(fn ($y) => $y->whereNull('year_from')->orWhere('year_from', '<=', $to))
                        ->where(fn ($y) => $y->whereNull('year_to')->orWhere('year_to', '>=', $from));
                });
        });
    }

    // ── Helpers ────────────────────────────────────────────────────
    public function discountPercent(): int
    {
        $mrp = (float) $this->mrp;

        return $mrp > 0 ? (int) round((($mrp - (float) $this->price) / $mrp) * 100) : 0;
    }

    public function availableStock(): int
    {
        return $this->inventory?->available() ?? 0;
    }

    public function stockStatus(): string
    {
        return $this->inventory?->status() ?? 'out_of_stock';
    }

    public function isPurchasable(int $qty = 1): bool
    {
        return $this->is_active && ($this->inventory?->canFulfil($qty) ?? false);
    }

    public function fitsVariant(VehicleVariant $variant, ?int $year = null): bool
    {
        return static::whereKey($this->id)->compatibleWith($variant, $year)->exists();
    }

    public function refreshRating(): void
    {
        $stats = $this->reviews()->where('status', 'approved')
            ->selectRaw('COUNT(*) as c, COALESCE(AVG(rating),0) as a')->first();
        $this->forceFill(['rating_count' => (int) $stats->c, 'rating_avg' => round((float) $stats->a, 2)])->saveQuietly();
    }
}
