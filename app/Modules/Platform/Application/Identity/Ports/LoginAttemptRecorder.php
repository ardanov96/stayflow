<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity\Ports;

use App\Modules\Platform\Domain\Identity\LoginFailureReason;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;

/**
 * Application port for recording and querying login attempts. The
 * `login_attempts` table is pre-authentication (no tenant context), so
 * the adapter does not use {@see BelongsToTenant}.
 */
interface LoginAttemptRecorder
{
    public function record(
        string $email,
        string $ipAddress,
        ?string $userAgent,
        bool $successful,
        ?LoginFailureReason $failureReason,
        ?int $userId,
    ): void;

    /**
     * Count failed attempts for the given email in the last
     * $withinSeconds. Used by the login throttle to decide whether
     * to short-circuit before doing a password verify.
     */
    public function countRecentFailuresForEmail(string $email, int $withinSeconds): int;

    /**
     * Count failed attempts from the given IP in the last
     * $withinSeconds. The secondary throttle key.
     */
    public function countRecentFailuresForIp(string $ipAddress, int $withinSeconds): int;
}
