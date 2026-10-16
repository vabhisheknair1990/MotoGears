<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCompatibility extends Model
{
    protected $table = 'product_vehicle_compatibilities';

    protected $fillable = [
        'product_id', 'vehicle_manufacturer_id', 'vehicle_model_id', 'vehicle_variant_id', 'notes',
    ];

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
        return implode(' → ', array_filter($parts));
    }
}
