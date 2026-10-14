<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Identity\Adapters;

use App\Modules\Platform\Application\Identity\Ports\LoginAttemptRecorder;
use App\Modules\Platform\Domain\Identity\LoginFailureReason;
use App\Modules\Platform\Infrastructure\Models\LoginAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed implementation of {@see LoginAttemptRecorder}.
 * Uses the `login_attempts` table (pre-auth, non-tenant). All
 * timestamp math is done in the application timezone via Carbon;
 * no conversion happens at the SQL boundary beyond the implicit
 * sqlite/utc round trip, which is fine because we always compare
 * against `now()` on the same DB connection.
 */
final class EloquentLoginAttemptRecorder implements LoginAttemptRecorder
{
    public function record(
        string $email,
        string $ipAddress,
        ?string $userAgent,
        bool $successful,
        ?LoginFailureReason $failureReason,
        ?int $userId,
    ): void {
        LoginAttempt::query()->create([
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'successful' => $successful,
            'failure_reason' => $failureReason?->value,
            'user_id' => $userId,
            'attempted_at' => now(),
        ]);
    }

    public function countRecentFailuresForEmail(string $email, int $withinSeconds): int
    {
        return (int) LoginAttempt::query()
            ->where('email', $email)
            ->where('successful', false)
            ->where('attempted_at', '>=', now()->subSeconds($withinSeconds))
            ->count();
    }

    public function countRecentFailuresForIp(string $ipAddress, int $withinSeconds): int
    {
        return (int) LoginAttempt::query()
            ->where('ip_address', $ipAddress)
            ->where('successful', false)
            ->where('attempted_at', '>=', now()->subSeconds($withinSeconds))
            ->count();
    }
}
