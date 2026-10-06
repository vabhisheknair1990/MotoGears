<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use FlushesStorefrontCache, HasFactory, SoftDeletes;

    protected $attributes = ['is_active' => true, 'is_featured' => false, 'sort_order' => 0];

    protected $fillable = [
        'name', 'slug', 'logo_path', 'description', 'website', 'country',
        'seo_title', 'seo_description', 'is_active', 'is_featured', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean', 'is_featured' => 'boolean', 'sort_order' => 'integer'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
