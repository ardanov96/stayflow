<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Domain\Identity\LoginFailureReason;

/**
 * Result of an authentication attempt. Three terminal cases:
 *
 *  - Authenticated: credentials (and 2FA, if required) are valid;
 *    the caller should establish the session and redirect to the
 *    intended URL.
 *  - TwoFactorRequired: credentials are valid but the user has 2FA
 *    enabled and has not yet provided the code; the caller should
 *    stash userId in the session and render the 2FA challenge page.
 *  - Failed: credentials are wrong, the account is locked, the
 *    email is unverified, or the IP/email is rate-limited. The
 *    reason is logged but NEVER reflected in the response (per
 *    AGENTS.md §3.3: no user enumeration through error messages).
 */
final readonly class AuthOutcome
{
    private function __construct(
        public AuthOutcomeStatus $status,
        public ?int $userId,
        public ?LoginFailureReason $failureReason,
    ) {}

    public static function authenticated(int $userId): self
    {
        return new self(AuthOutcomeStatus::AUTHENTICATED, $userId, null);
    }

    public static function twoFactorRequired(int $userId): self
    {
        return new self(AuthOutcomeStatus::TWO_FACTOR_REQUIRED, $userId, null);
    }

    public static function failed(LoginFailureReason $reason): self
    {
        return new self(AuthOutcomeStatus::FAILED, null, $reason);
    }

    public function isAuthenticated(): bool
    {
        return $this->status === AuthOutcomeStatus::AUTHENTICATED;
    }

    public function isTwoFactorRequired(): bool
    {
        return $this->status === AuthOutcomeStatus::TWO_FACTOR_REQUIRED;
    }

    public function isFailed(): bool
    {
        return $this->status === AuthOutcomeStatus::FAILED;
    }
}
