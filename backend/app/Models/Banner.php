<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use FlushesStorefrontCache;

    protected $attributes = ['is_active' => true, 'sort_order' => 0, 'placement' => 'hero', 'theme' => 'dark'];

    protected $fillable = [
        'title', 'subtitle', 'eyebrow', 'desktop_image_path', 'mobile_image_path', 'cta_label', 'cta_url',
        'placement', 'theme', 'starts_at', 'ends_at', 'is_active', 'sort_order',
    ];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'sort_order' => 'integer'];

    public function scopeLive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order');
    }
}
