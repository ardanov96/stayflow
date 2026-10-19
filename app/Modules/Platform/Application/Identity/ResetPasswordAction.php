<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;

/**
 * Complete a password reset. The caller already collected the
 * email + token + new password from the form. Returns the user id
 * on success, null on a bad / expired token. The controller must
 * render the same "your password has been reset" page either way
 * (enumeration defense).
 */
final readonly class ResetPasswordAction
{
    public function __construct(private UserIdentityGateway $users) {}

    public function execute(string $email, string $token, string $newPassword): ?int
    {
        $userId = $this->users->consumePasswordResetToken($email, $token);
        if ($userId === null) {
            return null;
        }

        $this->users->updatePassword($userId, $newPassword);
        // Reset locks and counters too: a forgotten password should
        // not leave the account throttled from a prior guessing
        // attack.
        $this->users->clearLock($userId);
        $this->users->resetFailedLogin($userId);

        return $userId;
    }
}
