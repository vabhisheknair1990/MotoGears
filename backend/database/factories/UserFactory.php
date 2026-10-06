<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+91 9'.fake()->numerify('#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->afterCreating(fn (User $u) => $u->forceFill(['is_active' => false])->save());
    }

    public function withRole(string $role): static
    {
        return $this->afterCreating(fn (User $u) => $u->assignRole($role));
    }

    public function customer(): static
    {
        return $this->withRole('customer');
    }
}
