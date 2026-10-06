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
        'vehicle_model_id', 'name', 'year_from', 'year_to', 'engine', 'fuel_type',
        'transmission', 'displacement_cc', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'year_from' => 'integer', 'year_to' => 'integer', 'displacement_cc' => 'integer'];

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForYear(Builder $q, ?int $year): Builder
    {
        return $year ? $q->where('year_from', '<=', $year)->where(fn ($w) => $w->whereNull('year_to')->orWhere('year_to', '>=', $year)) : $q;
    }

    public function yearRange(): string
    {
        return $this->year_from.'–'.($this->year_to ?? 'present');
    }

    public function years(): array
    {
        return range($this->year_from, $this->year_to ?? (int) date('Y'));
    }

    /** "Mahindra Thar 2.0 Petrol AT (2020–present)" */
    public function fullName(): string
    {
        $this->loadMissing('model.manufacturer');

        return trim($this->model->manufacturer->name.' '.$this->model->name.' '.$this->name);
    }
}
