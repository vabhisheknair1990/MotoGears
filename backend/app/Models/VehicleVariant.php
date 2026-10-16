<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleVariant extends Model
{
    use HasFactory;

    protected $attributes = ['is_active' => true];

    protected $fillable = [
        'vehicle_model_id', 'name', 'engine', 'fuel_type',
        'transmission', 'displacement_cc', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'displacement_cc' => 'integer'];

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->when(! \App\Support\Catalog::sellsCars(), fn ($q) => $q->whereHas('model', fn ($m) => $m->where('vehicle_type', '!=', 'car')));
    }

    /** "Royal Enfield Classic 350 Signals" */
    public function fullName(): string
    {
        $this->loadMissing('model.manufacturer');

        return trim($this->model->manufacturer->name.' '.$this->model->name.' '.$this->name);
    }
}
