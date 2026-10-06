<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $attributes = ['is_active' => true];

    protected $fillable = ['title', 'slug', 'content', 'meta_title', 'meta_description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
