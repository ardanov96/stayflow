<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Identity\Adapters;

use App\Models\User;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Application\Identity\UserDescriptor;
use App\Modules\Platform\Domain\Identity\TwoFactorSecret;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Eloquent-backed implementation of {@see UserIdentityGateway}.
 *
 * This is the ONLY class in the codebase that touches the `users`
 * table on behalf of the auth flow. The Application layer talks to
 * it through the interface; tests can substitute an in-memory
 * fake by rebinding the interface in the container.
 *
 * Conventions:
 *  - All reads return a {@see UserDescriptor} (Domain-friendly
 *    DTO) instead of the Eloquent User; callers cannot mutate
 *    the row through a descriptor.
 *  - All writes accept a `userId` rather than a User; the
 *    adapter loads the row inside the call so the Application
 *    stays free of Eloquent.
 *  - The 2FA secret is decrypted here (Laravel's `encrypted`
 *    cast is transparent on read); callers receive a
 *    {@see TwoFactorSecret} VO.
 */
final class EloquentUserIdentityGateway implements UserIdentityGateway
{
    public function findByEmail(string $email): ?UserDescriptor
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return null;
        }

        return $this->toDescriptor($user);
    }

    public function findById(int $userId): ?UserDescriptor
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return null;
        }

        return $this->toDescriptor($user);
    }

    public function verifyPassword(int $userId, string $plaintext): bool
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return false;
        }

        return Hash::check($plaintext, $user->password);
    }

    public function updatePassword(int $userId, string $plaintext): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }
        $user->password = $plaintext;
        $user->save();
    }

    public function isLocked(int $userId): bool
    {
        $user = User::query()->find($userId);

        return $user !== null && $user->isLocked();
    }

    public function lockUntil(int $userId, \DateTimeInterface $until): void
    {
        User::query()
            ->whereKey($userId)
            ->update(['locked_until' => $until]);
    }

    public function clearLock(int $userId): void
    {
        User::query()
            ->whereKey($userId)
            ->update(['locked_until' => null]);
    }

    public function incrementFailedLogin(int $userId): int
    {
        $affected = User::query()
            ->whereKey($userId)
            ->increment('failed_login_count');
        // increment returns the affected row count, not the new
        // value. We need the new value to compare against
        // MAX_FAILED_LOGINS in the Application action, so re-read.
        $newValue = (int) User::query()
            ->whereKey($userId)
            ->value('failed_login_count');
        if ($newValue === 0 && $affected === 0) {
            // row was missing
            return 0;
        }

        return $newValue;
    }

    public function resetFailedLogin(int $userId): void
    {
        User::query()
            ->whereKey($userId)
            ->update(['failed_login_count' => 0]);
    }

    public function recordSuccessfulLogin(int $userId, string $ipAddress): void
    {
        User::query()
            ->whereKey($userId)
            ->update([
                'last_login_at' => now(),
                'last_login_ip' => $ipAddress,
            ]);
    }

    public function isEmailVerified(int $userId): bool
    {
        $verifiedAt = User::query()
            ->whereKey($userId)
            ->value('email_verified_at');

        return $verifiedAt !== null;
    }

    public function markEmailVerified(int $userId): void
    {
        User::query()
            ->whereKey($userId)
            ->update(['email_verified_at' => now()]);
    }

    public function setUnconfirmedTwoFactorSecret(int $userId, TwoFactorSecret $secret): void
    {
        User::query()
            ->whereKey($userId)
            ->update([
                'two_factor_secret' => $secret->value,
                // Re-start enrollment: clear any previous
                // confirmation, recovery codes, and throttle
                // counters so a fresh secret really is a clean
                // slate.
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
            ]);
    }

    public function confirmTwoFactor(int $userId): void
    {
        User::query()
            ->whereKey($userId)
            ->update(['two_factor_confirmed_at' => now()]);
    }

    public function getTwoFactorSecret(int $userId): ?TwoFactorSecret
    {
        $raw = User::query()
            ->whereKey($userId)
            ->value('two_factor_secret');
        if ($raw === null || $raw === '') {
            return null;
        }

        return new TwoFactorSecret($raw);
    }

    public function disableTwoFactor(int $userId): void
    {
        User::query()
            ->whereKey($userId)
            ->update([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]);
    }

    public function replaceRecoveryCodes(int $userId, array $hashedCodes): void
    {
        User::query()
            ->whereKey($userId)
            ->update(['two_factor_recovery_codes' => array_values($hashedCodes)]);
    }

    public function consumeRecoveryCode(int $userId, string $hashedCode): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }
        $current = $user->two_factor_recovery_codes ?? [];
        $remaining = array_values(array_filter(
            $current,
            static fn ($h): bool => ! hash_equals((string) $h, $hashedCode),
        ));
        $user->two_factor_recovery_codes = $remaining;
        $user->save();
    }

    public function getRecoveryCodeHashes(int $userId): array
    {
        $hashes = User::query()
            ->whereKey($userId)
            ->value('two_factor_recovery_codes');

        return is_array($hashes) ? array_values($hashes) : [];
    }

    public function createPasswordResetToken(string $email): ?string
    {
        // Laravel's Password broker returns a constant string
        // (`Password::INVALID_USER`, `Password::RESET_LINK_SENT`,
        // `Password::RESET_THROTTLED`) for the public response;
        // the broker's internal createToken method returns the
        // raw token we need to hand off to the mailer.
        $broker = $this->broker();
        $token = $broker->createToken(User::query()->where('email', $email)->first());
        // The broker returns a string even when the email is
        // unknown (it doesn't leak the existence to the caller
        // here, it just returns a throwaway token). The Application
        // side still only sends the mail when findByEmail was
        // non-null, so the leak window is closed at the action.
        return $token;
    }

    public function consumePasswordResetToken(string $email, string $token): ?int
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return null;
        }
        if (! $this->broker()->tokenExists($user, $token)) {
            return null;
        }
        $this->broker()->deleteToken($user);

        return (int) $user->getKey();
    }

    private function broker(): PasswordBroker
    {
        // Resolve through the manager so the same broker
        // configuration (hashing, throttling) is used everywhere.
        $manager = app(PasswordBrokerManager::class);

        return $manager->broker();
    }

    private function toDescriptor(User $user): UserDescriptor
    {
        return new UserDescriptor(
            id: (int) $user->getKey(),
            email: (string) $user->email,
            name: (string) $user->name,
            emailVerified: $user->email_verified_at !== null,
            twoFactorConfirmed: $user->hasTwoFactor(),
            isLocked: $user->isLocked(),
            lockedUntil: $user->locked_until,
            requiresTwoFactor: $user->requiresTwoFactor(),
        );
    }
}