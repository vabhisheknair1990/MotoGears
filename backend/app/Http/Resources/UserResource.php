<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => Media::url($this->avatar_path),
            'marketing_opt_in' => (bool) $this->marketing_opt_in,
            'is_active' => (bool) $this->is_active,
            'is_staff' => $this->isStaff(),
            'roles' => $this->roles->map(fn ($r) => ['name' => $r->name, 'label' => $r->label])->values(),
            'permissions' => $this->when($this->isStaff(), fn () => $this->isSuperAdmin() ? ['*'] : $this->permissionNames()->values()),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
