<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Application\Identity\Ports\AuthMailer;
use App\Modules\Platform\Application\Identity\Ports\LoginAttemptRecorder;
use App\Modules\Platform\Application\Identity\Ports\TotpGenerator;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Domain\Identity\LoginFailureReason;
use App\Modules\Platform\Domain\Identity\OtpWindow;
use App\Modules\Platform\Domain\Identity\RecoveryCode;
use App\Modules\Platform\Domain\Identity\TwoFactorSecret;

/**
 * Step 2 of the login flow: verify a TOTP code OR a recovery code.
 * The caller hands us the user id (already established by
 * {@see AuthenticateUserAction} and stashed in the session under a
 * "pending 2FA" marker) plus whatever the user typed in the
 * challenge form.
 *
 * Inputs are auto-dispatched:
 *  - 6 digits: TOTP path
 *  - 10 chars in the recovery-code alphabet: recovery path
 *  - anything else: failed with INVALID_CREDENTIALS
 */
final readonly class CompleteTwoFactorLoginAction
{
    public function __construct(
        private UserIdentityGateway $users,
        private TotpGenerator $totp,
        private LoginAttemptRecorder $attempts,
        private AuthMailer $mailer,
    ) {
    }

    public function execute(
        int $userId,
        string $input,
        string $ipAddress,
        ?string $userAgent,
    ): AuthOutcome {
        $user = $this->users->findById($userId);
        if ($user === null || ! $user->twoFactorConfirmed) {
            return AuthOutcome::failed(LoginFailureReason::TWO_FACTOR_FAILED);
        }

        $secret = $this->users->getTwoFactorSecret($userId);
        if ($secret === null) {
            // Confirmed-but-no-secret is a data-integrity bug; treat
            // it like a failed attempt to be safe.
            return AuthOutcome::failed(LoginFailureReason::TWO_FACTOR_FAILED);
        }

        $normalized = strtoupper(trim($input));
        $matched = $this->matchInput($secret, $userId, $normalized);

        if (! $matched) {
            $this->attempts->record(
                email: $user->email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                successful: false,
                failureReason: LoginFailureReason::TWO_FACTOR_FAILED,
                userId: $user->id,
            );

            return AuthOutcome::failed(LoginFailureReason::TWO_FACTOR_FAILED);
        }

        $this->users->resetFailedLogin($userId);
        $this->users->recordSuccessfulLogin($userId, $ipAddress);
        $this->attempts->record(
            email: $user->email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            successful: true,
            failureReason: null,
            userId: $user->id,
        );

        return AuthOutcome::authenticated($userId);
    }

    private function matchInput(TwoFactorSecret $secret, int $userId, string $input): bool
    {
        // TOTP path: 6 digits.
        if (preg_match('/^\d{6}$/', $input) === 1) {
            return $this->totp->verify($secret, $input, OtpWindow::lenient());
        }

        // Recovery path: 10 chars in the recovery alphabet. We try
        // each stored hash; consume on match. Malformed input never
        // reaches password_verify, so it cannot succeed against any
        // hash (avoids a partial-match oracle).
        if (preg_match('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{10}$/', $input) === 1) {
            $hashes = $this->users->getRecoveryCodeHashes($userId);
            foreach ($hashes as $hash) {
                if (RecoveryCode::verifyAgainst($input, $hash)) {
                    $this->users->consumeRecoveryCode($userId, $hash);
                    $remaining = count($hashes) - 1;
                    $this->mailer->sendRecoveryCodeUsedNotice($userId, (string) $remaining);

                    return true;
                }
            }
        }

        return false;
    }
}