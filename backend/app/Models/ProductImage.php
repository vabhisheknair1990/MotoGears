<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'alt', 'is_primary', 'sort_order'];

    protected $casts = ['is_primary' => 'boolean', 'sort_order' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): ?string
    {
        return Media::url($this->path);
    }
}
