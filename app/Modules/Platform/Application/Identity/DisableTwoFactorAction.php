<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;

/**
 * Turn off 2FA for a user. Wipes the secret, the recovery codes,
 * and the confirmed-at timestamp. Idempotent.
 */
final readonly class DisableTwoFactorAction
{
    public function __construct(private UserIdentityGateway $users) {}

    public function execute(int $userId): void
    {
        $this->users->disableTwoFactor($userId);
    }
}