<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Issues the Sanctum bearer token + user payload returned by login, register and guest sign-up. */
class AuthTokenService
{
    public function issue(User $user, Request $request, string $scope = 'storefront'): array
    {
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $name = ($scope === 'admin' ? 'admin:' : 'web:').Str::limit((string) ($request->input('device_name') ?: $request->userAgent() ?: 'client'), 80, '');
        $abilities = $scope === 'admin' ? ['admin', 'storefront'] : ['storefront'];
        $expires = $request->boolean('remember') ? now()->addDays(30) : now()->addMinutes((int) config('sanctum.expiration'));
        $token = $user->createToken($name, $abilities, $expires);

        return [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expires->toIso8601String(),
            'user' => (new UserResource($user->load('roles.permissions')))->resolve($request),
        ];
    }
}
