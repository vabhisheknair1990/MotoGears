<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $attributes = ['is_active' => true, 'marketing_opt_in' => false];

    protected $fillable = ['name', 'email', 'phone', 'password', 'avatar_path', 'marketing_opt_in'];

    protected $hidden = ['password', 'remember_token'];

    private ?Collection $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'marketing_opt_in' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function wishlist(): HasOne
    {
        return $this->hasOne(Wishlist::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(CustomerVehicle::class);
    }

    // ── Roles & permissions ────────────────────────────────────────
    public function hasRole(string ...$roles): bool
    {
        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    public function isStaff(): bool
    {
        return $this->roles->contains(fn (Role $r) => $r->is_staff);
    }

    public function permissionNames(): Collection
    {
        if ($this->permissionCache === null) {
            $this->loadMissing('roles.permissions');
            $this->permissionCache = $this->roles->flatMap(fn (Role $r) => $r->permissions->pluck('name'))->unique()->values();
        }

        return $this->permissionCache;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isSuperAdmin() || $this->permissionNames()->contains($permission);
    }

    public function assignRole(string ...$roles): static
    {
        $ids = Role::whereIn('name', $roles)->pluck('id');
        $this->roles()->syncWithoutDetaching($ids);
        $this->unsetRelation('roles');
        $this->permissionCache = null;

        return $this;
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereHas('roles', fn ($q) => $q->where('name', Role::CUSTOMER));
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereHas('roles', fn ($q) => $q->where('is_staff', true));
    }

    public function defaultVehicle(): ?CustomerVehicle
    {
        return $this->vehicles()->where('is_default', true)->first();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
