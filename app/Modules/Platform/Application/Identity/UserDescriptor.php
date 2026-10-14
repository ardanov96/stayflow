<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

/**
 * Plain DTO representing a user for the Application layer. Hides the
 * Eloquent User model from anything that would otherwise have to
 * import it. Constructed by the Infrastructure
 * {@see \App\Modules\Platform\Infrastructure\Identity\Adapters\EloquentUserIdentityGateway}.
 */
final readonly class UserDescriptor
{
    /**
     * True when the user holds a Membership with role `owner` or
     * `manager` somewhere. Mirrors the rule applied at login time:
     * such users MUST have 2FA confirmed; the EnsureTwoFactorIs
     * Confirmed middleware enforces it. Memberships can change at
     * any time (invite, role update) so this is re-evaluated on
     * every read by the gateway.
     */
    public bool $requiresTwoFactor;

    public function __construct(
        public int $id,
        public string $email,
        public string $name,
        public bool $emailVerified,
        public bool $twoFactorConfirmed,
        public bool $isLocked,
        public ?\DateTimeInterface $lockedUntil,
        bool $requiresTwoFactor = false,
    ) {
        $this->requiresTwoFactor = $requiresTwoFactor;
    }
}