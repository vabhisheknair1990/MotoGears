<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_customer_can_register_and_receives_token(): void
    {
        $res = $this->postJson($this->api('auth/register'), [
            'name' => 'Asha Kumar', 'email' => 'Asha@Example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at', 'user' => ['id', 'email', 'roles']]])
            ->assertJsonPath('data.user.email', 'asha@example.com')
            ->assertJsonPath('data.user.roles.0.name', 'customer');
        $this->assertDatabaseHas('wishlists', ['user_id' => $res->json('data.user.id')]);
    }

    public function test_register_validation_uses_standard_error_envelope(): void
    {
        $this->postJson($this->api('auth/register'), ['email' => 'not-an-email', 'password' => 'short'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_with_valid_and_invalid_credentials(): void
    {
        $user = $this->customer(['email' => 'rider@example.com']);

        $this->postJson($this->api('auth/login'), ['email' => 'rider@example.com', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $token = $this->postJson($this->api('auth/login'), ['email' => 'rider@example.com', 'password' => 'password'])
            ->assertOk()->json('data.token');

        $this->withToken($token)->getJson($this->api('me'))->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        User::factory()->inactive()->customer()->create(['email' => 'blocked@example.com']);

        $this->postJson($this->api('auth/login'), ['email' => 'blocked@example.com', 'password' => 'password'])->assertStatus(403);
    }

    public function test_protected_routes_return_json_401(): void
    {
        $this->getJson($this->api('me'))
            ->assertStatus(401)
            ->assertExactJson(['success' => false, 'message' => 'Unauthenticated. Please log in.']);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $this->customer(['email' => 'out@example.com']);
        $token = $this->postJson($this->api('auth/login'), ['email' => 'out@example.com', 'password' => 'password'])->json('data.token');

        $this->withToken($token)->postJson($this->api('auth/logout'))->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_forgot_and_reset_password(): void
    {
        Notification::fake();
        $user = $this->customer(['email' => 'forgot@example.com']);

        $this->postJson($this->api('auth/forgot-password'), ['email' => 'forgot@example.com'])->assertOk();
        // Unknown emails get the same response (no account enumeration).
        $this->postJson($this->api('auth/forgot-password'), ['email' => 'nobody@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $token = Password::broker()->createToken($user);

        $this->postJson($this->api('auth/reset-password'), [
            'token' => $token, 'email' => 'forgot@example.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertOk();

        $this->postJson($this->api('auth/login'), ['email' => 'forgot@example.com', 'password' => 'newpass123'])->assertOk();
    }

    public function test_change_password_requires_current_password(): void
    {
        $this->actingAsCustomer();

        $this->putJson($this->api('me/password'), ['current_password' => 'nope', 'password' => 'another123', 'password_confirmation' => 'another123'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->putJson($this->api('me/password'), ['current_password' => 'password', 'password' => 'another123', 'password_confirmation' => 'another123'])
            ->assertOk();
    }

    public function test_profile_update(): void
    {
        $this->actingAsCustomer();

        $this->patchJson($this->api('me'), ['name' => 'New Name', 'phone' => '+91 98450 12345'])
            ->assertOk()->assertJsonPath('data.name', 'New Name');
    }

    public function test_admin_login_rejects_customers_and_accepts_staff(): void
    {
        $this->customer(['email' => 'cust@example.com']);
        $this->staff('admin', ['email' => 'boss@example.com']);

        $this->postJson($this->api('admin/auth/login'), ['email' => 'cust@example.com', 'password' => 'password'])->assertStatus(403);
        $this->postJson($this->api('admin/auth/login'), ['email' => 'boss@example.com', 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.user.is_staff', true);
    }
}
