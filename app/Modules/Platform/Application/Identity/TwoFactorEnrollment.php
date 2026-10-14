<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Domain\Identity\RecoveryCode;
use App\Modules\Platform\Domain\Identity\TwoFactorSecret;

/**
 * Result of a confirmed 2FA enrollment. The caller MUST show
 * `$plaintextRecoveryCodes` to the user exactly once and never
 * again — only the hashes are persisted.
 */
final readonly class TwoFactorEnrollment
{
    /**
     * @param  list<RecoveryCode>  $plaintextRecoveryCodes  shown once, never stored
     */
    public function __construct(
        public TwoFactorSecret $secret,
        public array $plaintextRecoveryCodes,
    ) {}
}
