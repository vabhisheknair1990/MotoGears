<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const CATALOG_MANAGER = 'catalog_manager';
    public const ORDER_MANAGER = 'order_manager';
    public const INVENTORY_MANAGER = 'inventory_manager';
    public const CONTENT_MANAGER = 'content_manager';
    public const CUSTOMER = 'customer';

    protected $fillable = ['name', 'label', 'description', 'is_staff'];

    protected $casts = ['is_staff' => 'boolean'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
