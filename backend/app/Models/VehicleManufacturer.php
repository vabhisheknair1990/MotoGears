<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class VehicleManufacturer extends Model
{
    use HasFactory;

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected $fillable = ['name', 'slug', 'logo_path', 'vehicle_type', 'country', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class)->orderBy('name');
    }

    public function variants(): HasManyThrough
    {
        return $this->hasManyThrough(VehicleVariant::class, VehicleModel::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForType(Builder $q, ?string $type): Builder
    {
        return $type ? $q->whereIn('vehicle_type', [$type, 'both']) : $q;
    }

    public function logoUrl(): ?string
    {
        return Media::url($this->logo_path);
    }
}
