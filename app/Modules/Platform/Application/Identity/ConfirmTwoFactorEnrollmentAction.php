<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Application\Identity\Ports\TotpGenerator;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Domain\Identity\OtpWindow;
use App\Modules\Platform\Domain\Identity\RecoveryCode;

/**
 * Finalize a 2FA enrollment: verify the user can produce a valid
 * TOTP code from the freshly-generated secret, then promote the
 * secret to "confirmed" and issue a fresh set of recovery codes.
 *
 * The recovery codes are returned in PLAINTEXT exactly once (this
 * call's return value) and only the hashes are persisted. The
 * caller MUST show them on the next page render and never again.
 */
final readonly class ConfirmTwoFactorEnrollmentAction
{
    /** Number of recovery codes issued at enrollment / regeneration. */
    public const RECOVERY_CODE_COUNT = 10;

    public function __construct(
        private UserIdentityGateway $users,
        private TotpGenerator $totp,
    ) {
    }

    public function execute(int $userId, string $totpCode): ?TwoFactorEnrollment
    {
        $secret = $this->users->getTwoFactorSecret($userId);
        if ($secret === null) {
            return null;
        }

        $normalized = trim($totpCode);
        if (! $this->totp->verify($secret, $normalized, OtpWindow::lenient())) {
            return null;
        }

        $this->users->confirmTwoFactor($userId);

        $codes = [];
        $hashes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = RecoveryCode::generate();
            $codes[] = $code;
            $hashes[] = $code->hash();
        }
        $this->users->replaceRecoveryCodes($userId, $hashes);

        return new TwoFactorEnrollment($secret, $codes);
    }
}