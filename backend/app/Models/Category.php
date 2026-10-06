<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use FlushesStorefrontCache, HasFactory, SoftDeletes;

    protected $attributes = ['is_active' => true, 'is_featured' => false, 'sort_order' => 0, 'vehicle_type' => 'universal'];

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image_path', 'icon', 'vehicle_type',
        'seo_title', 'seo_description', 'is_active', 'is_featured', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean', 'is_featured' => 'boolean', 'sort_order' => 'integer'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    /** IDs of this category and all descendants (categories are two/three levels deep). */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];
        while ($frontier) {
            $frontier = static::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
