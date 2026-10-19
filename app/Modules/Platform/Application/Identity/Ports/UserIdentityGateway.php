<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity\Ports;

use App\Modules\Platform\Application\Identity\UserDescriptor;
use App\Modules\Platform\Domain\Identity\RecoveryCode;
use App\Modules\Platform\Domain\Identity\TwoFactorSecret;
use App\Modules\Platform\Infrastructure\Identity\Adapters\EloquentUserIdentityGateway;
use DateTimeInterface;

/**
 * Application port that hides the User Eloquent model from the
 * Application layer (Domain and Application must not import Eloquent,
 * enforced by tests/Arch/EloquentBoundariesTest). The Infrastructure
 * adapter
 * {@see EloquentUserIdentityGateway}
 * is the only class that touches the User model on behalf of auth.
 *
 * All mutating methods are idempotent; reading and writing is
 * wrapped in transactions by the adapter when multiple fields must
 * change together (set 2FA secret + confirm in one go, for example).
 */
interface UserIdentityGateway
{
    public function findByEmail(string $email): ?UserDescriptor;

    public function findById(int $userId): ?UserDescriptor;

    /**
     * Constant-time credential check. Returns true when the
     * plaintext password matches the stored hash. NEVER echoes
     * whether the email exists; callers must call findByEmail
     * first and decide what to do with a null result.
     */
    public function verifyPassword(int $userId, string $plaintext): bool;

    /**
     * Update the user's password hash. The plaintext is hashed
     * inside the adapter (Laravel `hashed` cast on save).
     */
    public function updatePassword(int $userId, string $plaintext): void;

    public function isLocked(int $userId): bool;

    public function lockUntil(int $userId, DateTimeInterface $until): void;

    public function clearLock(int $userId): void;

    public function incrementFailedLogin(int $userId): int;

    public function resetFailedLogin(int $userId): void;

    public function recordSuccessfulLogin(int $userId, string $ipAddress): void;

    public function isEmailVerified(int $userId): bool;

    public function markEmailVerified(int $userId): void;

    /**
     * Store an unconfirmed TOTP secret. The Infrastructure layer is
     * responsible for encrypting the secret at rest (Laravel
     * `encrypted` cast).
     */
    public function setUnconfirmedTwoFactorSecret(int $userId, TwoFactorSecret $secret): void;

    /**
     * Promote the unconfirmed secret to confirmed. Idempotent: calling
     * twice with a valid code is a no-op the second time.
     */
    public function confirmTwoFactor(int $userId): void;

    /**
     * Return the stored (encrypted) TOTP secret, or null if the
     * user has no secret at all. The secret is decrypted by the
     * Infrastructure adapter before being handed to the Application.
     */
    public function getTwoFactorSecret(int $userId): ?TwoFactorSecret;

    /**
     * @return list<string> Argon2id hashes of the user's recovery
     *                      codes, or an empty list when none.
     */
    public function getRecoveryCodeHashes(int $userId): array;

    /**
     * Disable 2FA entirely. Wipes the secret, the recovery codes,
     * and the confirmed-at timestamp.
     */
    public function disableTwoFactor(int $userId): void;

    /**
     * Replace the user's full recovery-codes list with the given
     * hashes. Pass an empty array to remove all codes. The hashes
     * are Argon2id outputs of {@see RecoveryCode::hash()}.
     *
     * @param  list<string>  $hashedCodes
     */
    public function replaceRecoveryCodes(int $userId, array $hashedCodes): void;

    /**
     * Remove a single hashed recovery code after a successful use.
     * No-op if the hash is not present.
     */
    public function consumeRecoveryCode(int $userId, string $hashedCode): void;

    /**
     * Issue and persist a fresh password-reset token for the given
     * email. Returns null when the email is unknown (callers MUST
     * not reveal that fact to the user; this method exists so the
     * adapter can use Laravel's Password broker consistently).
     *
     * Returns a string token even when the email is unknown (the
     * broker returns a throwaway token; the Application layer is
     * the gate that prevents sending the mail in that case). The
     * port signature stays `string` so the Application does not
     * have to null-check a value it is going to use unconditionally.
     */
    public function createPasswordResetToken(string $email): string;

    /**
     * Validate a (email, token) pair against the password-reset
     * broker and return the matching user id, or null when the
     * token is invalid / expired / the email is unknown.
     */
    public function consumePasswordResetToken(string $email, string $token): ?int;
}
