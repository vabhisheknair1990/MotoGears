<?php

namespace App\Models;

use App\Models\Concerns\FlushesStorefrontCache;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use FlushesStorefrontCache;

    protected $attributes = ['is_active' => true, 'sort_order' => 0, 'rating' => 5];

    protected $fillable = ['name', 'location', 'vehicle', 'rating', 'content', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'rating' => 'integer', 'sort_order' => 'integer'];
}
