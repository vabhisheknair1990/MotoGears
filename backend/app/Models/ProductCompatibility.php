<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCompatibility extends Model
{
    protected $table = 'product_vehicle_compatibilities';

    protected $fillable = [
        'product_id', 'vehicle_manufacturer_id', 'vehicle_model_id', 'vehicle_variant_id', 'year_from', 'year_to', 'notes',
    ];

    protected $casts = ['year_from' => 'integer', 'year_to' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(VehicleManufacturer::class, 'vehicle_manufacturer_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(VehicleVariant::class, 'vehicle_variant_id');
    }

    public function label(): string
    {
        $parts = [$this->manufacturer?->name, $this->model?->name ?? 'All models', $this->variant?->name ?? ($this->model ? 'All variants' : null)];
        $label = implode(' → ', array_filter($parts));
        if ($this->year_from || $this->year_to) {
            $label .= ' ('.($this->year_from ?? '…').'–'.($this->year_to ?? 'present').')';
        }

        return $label;
    }
}
