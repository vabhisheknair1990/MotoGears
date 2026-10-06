<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\AuditLogger;
use App\Services\CartService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private CartService $carts, private AuditLogger $audit) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->only(['name', 'email', 'phone', 'password', 'marketing_opt_in']));
            $user->assignRole(Role::CUSTOMER);
            Wishlist::create(['user_id' => $user->id]);

            return $user;
        });

        $this->carts->mergeGuestCart($request->header(CartService::TOKEN_HEADER), $user);

        return $this->created($this->tokenPayload($user, $request), 'Account created successfully');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->attempt($request);
        $this->carts->mergeGuestCart($request->header(CartService::TOKEN_HEADER), $user);

        return $this->ok($this->tokenPayload($user, $request), 'Logged in successfully');
    }

    /** Admin panel login: same credentials check, but only staff accounts are accepted. */
    public function adminLogin(LoginRequest $request): JsonResponse
    {
        $user = $this->attempt($request);
        if (! $user->isStaff()) {
            throw ValidationException::withMessages(['email' => ['This account does not have admin access.']])->status(403);
        }
        $this->audit->log('auth.admin_login', $user, null, null, $user);

        return $this->ok($this->tokenPayload($user, $request, 'admin'), 'Logged in successfully');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Logged out successfully');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Always return the same response so the endpoint can't be used to discover accounts.
        Password::sendResetLink(['email' => strtolower($request->email)]);

        return $this->ok(null, 'If an account exists for that email, a password reset link has been sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            ['email' => strtolower($request->email), 'password' => $request->password, 'password_confirmation' => $request->password_confirmation, 'token' => $request->token],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return $this->ok(null, 'Your password has been reset. You can now log in.');
    }

    private function attempt(LoginRequest $request): User
    {
        $user = User::with('roles.permissions')->where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']])->status(422);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => ['This account has been disabled. Please contact support.']])->status(403);
        }

        return $user;
    }

    private function tokenPayload(User $user, Request $request, string $scope = 'storefront'): array
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
