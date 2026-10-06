<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerVehicle extends Model
{
    protected $attributes = ['is_default' => false];

    protected $fillable = ['user_id', 'vehicle_variant_id', 'year', 'nickname', 'registration_number', 'is_default'];

    protected $casts = ['is_default' => 'boolean', 'year' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(VehicleVariant::class, 'vehicle_variant_id');
    }
}
