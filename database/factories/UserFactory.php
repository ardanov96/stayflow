<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Platform\Application\Identity\AuthenticateUserAction;
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
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
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
     * State: 2FA is configured AND confirmed. The secret and
     * recovery-codes fields are filled with deterministic placeholders;
     * tests that exercise the TOTP path should overwrite
     * `two_factor_secret` with a known value and use
     * `Google2faTotpGenerator` (or stub it) to generate a code.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (): array => [
            'two_factor_secret' => encrypt(str_repeat('A', 32)),
            'two_factor_recovery_codes' => encrypt([
                password_hash(str_repeat('B', 10), PASSWORD_ARGON2ID),
            ]),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * State: the user is currently locked out of password login.
     * The lock will expire 15 minutes in the future (matches
     * {@see AuthenticateUserAction::LOCKOUT_DURATION_SECONDS}).
     */
    public function locked(): static
    {
        return $this->state(fn (): array => [
            'failed_login_count' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);
    }
}
