<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleModel extends Model
{
    use HasFactory;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['vehicle_manufacturer_id', 'name', 'slug', 'vehicle_type', 'body_type', 'image_path', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(VehicleManufacturer::class, 'vehicle_manufacturer_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(VehicleVariant::class)->orderBy('name');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
