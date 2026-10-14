<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Identity\Adapters;

use App\Models\User;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Application\Identity\UserDescriptor;
use App\Modules\Platform\Domain\Identity\TwoFactorSecret;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

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

    public function lockUntil(int $userId, DateTimeInterface $until): void
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
        User::query()
            ->whereKey($userId)
            ->increment('failed_login_count');

        return (int) User::query()
            ->whereKey($userId)
            ->value('failed_login_count');
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

        return new TwoFactorSecret((string) $raw);
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
        // The Eloquent `encrypted:array` cast expects a list at
        // write time; the port already constrains the type.
        User::query()
            ->whereKey($userId)
            ->update(['two_factor_recovery_codes' => $hashedCodes]);
    }

    public function consumeRecoveryCode(int $userId, string $hashedCode): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }
        $raw = $user->getAttribute('two_factor_recovery_codes');
        /** @var array<int, string> $current */
        $current = is_array($raw) ? $raw : [];
        $remaining = array_values(array_filter(
            $current,
            static fn (string $h): bool => ! hash_equals($h, $hashedCode),
        ));
        $user->setAttribute('two_factor_recovery_codes', $remaining);
        $user->save();
    }

    public function getRecoveryCodeHashes(int $userId): array
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return [];
        }
        $hashes = $user->getAttribute('two_factor_recovery_codes');

        return is_array($hashes) ? array_values(array_map('strval', $hashes)) : [];
    }

    public function createPasswordResetToken(string $email): string
    {
        $broker = $this->broker();
        $user = User::query()->where('email', $email)->first();

        return (string) $broker->createToken($user);
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
        return Password::broker();
    }

    private function toDescriptor(User $user): UserDescriptor
    {
        $lockedUntilRaw = $user->getAttribute('locked_until');
        $lockedUntil = null;
        if ($lockedUntilRaw instanceof DateTimeInterface) {
            $lockedUntil = $lockedUntilRaw;
        } elseif (is_string($lockedUntilRaw) && $lockedUntilRaw !== '') {
            $lockedUntil = new DateTimeImmutable($lockedUntilRaw);
        }

        return new UserDescriptor(
            id: (int) $user->getKey(),
            email: (string) $user->email,
            name: (string) $user->name,
            emailVerified: $user->email_verified_at !== null,
            twoFactorConfirmed: $user->hasTwoFactor(),
            isLocked: $user->isLocked(),
            lockedUntil: $lockedUntil,
            requiresTwoFactor: $user->requiresTwoFactor(),
        );
    }
}
