<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $attributes = ['is_active' => true, 'sort_order' => 0, 'category' => 'General'];

    protected $fillable = ['category', 'question', 'answer', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];
}
