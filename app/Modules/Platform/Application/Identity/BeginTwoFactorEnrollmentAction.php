<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Application\Identity\Ports\TotpGenerator;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Domain\Identity\TwoFactorSecret;

/**
 * Start a 2FA enrollment: generate a fresh TOTP secret, persist it
 * in the "unconfirmed" state, and return the plaintext secret for
 * the QR-code page. The secret is NOT shown again — the user must
 * scan it into their authenticator app, then call
 * {@see ConfirmTwoFactorEnrollmentAction} with a valid code.
 */
final readonly class BeginTwoFactorEnrollmentAction
{
    public function __construct(
        private UserIdentityGateway $users,
        private TotpGenerator $totp,
    ) {}

    public function execute(int $userId): TwoFactorSecret
    {
        $secret = $this->totp->generateSecret();
        $this->users->setUnconfirmedTwoFactorSecret($userId, $secret);

        return $secret;
    }
}
