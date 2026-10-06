<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use FlushesStorefrontCache, SoftDeletes;

    protected $attributes = ['status' => 'draft', 'reading_minutes' => 3];

    protected $fillable = [
        'blog_category_id', 'author_id', 'title', 'slug', 'excerpt', 'content', 'cover_image_path',
        'status', 'published_at', 'reading_minutes', 'meta_title', 'meta_description',
    ];

    protected $casts = ['published_at' => 'datetime', 'reading_minutes' => 'integer'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->where('published_at', '<=', now());
    }
}
