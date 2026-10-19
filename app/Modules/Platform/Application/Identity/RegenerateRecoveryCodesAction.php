<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Domain\Identity\RecoveryCode;

/**
 * Issue a fresh set of recovery codes. The previous codes are
 * invalidated the moment this is called. Same plaintext-show-once
 * contract as the initial enrollment.
 */
final readonly class RegenerateRecoveryCodesAction
{
    public const RECOVERY_CODE_COUNT = ConfirmTwoFactorEnrollmentAction::RECOVERY_CODE_COUNT;

    public function __construct(private UserIdentityGateway $users) {}

    /**
     * @return list<RecoveryCode>
     */
    public function execute(int $userId): array
    {
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = RecoveryCode::generate();
            $codes[] = $code;
            $hashes[] = $code->hash();
        }
        $this->users->replaceRecoveryCodes($userId, $hashes);

        return $codes;
    }
}
