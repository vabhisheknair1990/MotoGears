<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    protected $attributes = ['is_filterable' => true, 'sort_order' => 0];

    protected $fillable = ['name', 'slug', 'is_filterable', 'sort_order'];

    protected $casts = ['is_filterable' => 'boolean'];

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order')->orderBy('value');
    }
}
