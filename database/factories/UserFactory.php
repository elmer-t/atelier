<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * A commenting Client: a passwordless, unverified User (ADR-0003).
     */
    public function client(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Client,
            'password' => null,
            'email_verified_at' => null,
            'remember_token' => null,
        ]);
    }

    /**
     * The dedicated non-human Agent User (ADR-0006).
     */
    public function agent(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Agent,
            'password' => null,
            'email_verified_at' => null,
            'remember_token' => null,
        ]);
    }

    /**
     * A User a Creator has cut off: kept for their history, barred from commenting.
     */
    public function deactivated(): static
    {
        return $this->state(fn () => [
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
