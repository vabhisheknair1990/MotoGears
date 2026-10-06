<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['country' => 'IN', 'is_default' => false, 'label' => 'Home'];

    protected $fillable = [
        'label', 'name', 'phone', 'line1', 'line2', 'landmark', 'city', 'state', 'postal_code', 'country', 'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Immutable snapshot stored on orders. */
    public function toSnapshot(): array
    {
        return $this->only(['name', 'phone', 'line1', 'line2', 'landmark', 'city', 'state', 'postal_code', 'country']);
    }
}
