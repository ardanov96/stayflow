<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

use App\Modules\Platform\Application\Identity\Ports\LoginAttemptRecorder;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Domain\Identity\LoginFailureReason;
use DateTimeImmutable;
use Illuminate\Support\Facades\Hash;

/**
 * Step 1 of the login flow: password check + lockout + throttle.
 *
 * Returns {@see AuthOutcome}. The controller is responsible for
 * actually establishing the session (Auth::loginUsingId) on success;
 * the Application layer is facades-and-IO-free and only computes the
 * outcome.
 *
 * Security properties (AGENTS.md §3.3 — no user enumeration):
 *
 *  - When the email is unknown, the action still runs a constant-time
 *    password hash so the response time of "unknown email" matches
 *    "wrong password". The returned outcome is Failed in both cases
 *    with no distinguishing field.
 *  - When the account is locked or the IP/email is over the rate
 *    limit, the action still records a (single) attempt and still
 *    returns Failed. The caller must not reveal which.
 *  - Every call writes exactly one row to login_attempts, whether
 *    successful or not, so audit is complete.
 */
final readonly class AuthenticateUserAction
{
    /**
     * Five failed attempts within {@see LOCKOUT_AFTER_SECONDS}
     * locks the account. Five is the OWASP-recommended default for
     * interactive login in 2026.
     */
    public const MAX_FAILED_LOGINS = 5;

    /** Lockout lasts 15 minutes. */
    public const LOCKOUT_DURATION_SECONDS = 900;

    /** Sliding window for the secondary email+IP rate limit. */
    public const RATE_LIMIT_WINDOW_SECONDS = 60;

    /** More than 20 failures from one IP in 60s returns rate_limited. */
    public const RATE_LIMIT_MAX_PER_IP = 20;

    public function __construct(
        private UserIdentityGateway $users,
        private LoginAttemptRecorder $attempts,
    ) {}

    public function execute(
        string $email,
        string $password,
        string $ipAddress,
        ?string $userAgent,
    ): AuthOutcome {
        $user = $this->users->findByEmail($email);

        // Unknown email: do a dummy hash to keep timing constant,
        // then record + return failed. The userAgent is recorded
        // with the same shape as a real attempt so the audit trail
        // is uniform (caller decides whether to even render the
        // message; ours says "invalid credentials").
        if ($user === null) {
            Hash::driver('bcrypt')->make($password);
            $this->attempts->record(
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                successful: false,
                failureReason: LoginFailureReason::INVALID_CREDENTIALS,
                userId: null,
            );

            return AuthOutcome::failed(LoginFailureReason::INVALID_CREDENTIALS);
        }

        if ($user->isLocked) {
            $this->attempts->record(
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                successful: false,
                failureReason: LoginFailureReason::ACCOUNT_LOCKED,
                userId: $user->id,
            );

            return AuthOutcome::failed(LoginFailureReason::ACCOUNT_LOCKED);
        }

        // Secondary throttle: too many failures from this IP in the
        // last minute, regardless of which account. Cheap bot
        // protection that runs before the bcrypt verify.
        $recentIpFailures = $this->attempts->countRecentFailuresForIp(
            $ipAddress,
            self::RATE_LIMIT_WINDOW_SECONDS,
        );
        if ($recentIpFailures >= self::RATE_LIMIT_MAX_PER_IP) {
            $this->attempts->record(
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                successful: false,
                failureReason: LoginFailureReason::RATE_LIMITED,
                userId: $user->id,
            );

            return AuthOutcome::failed(LoginFailureReason::RATE_LIMITED);
        }

        if (! $this->users->verifyPassword($user->id, $password)) {
            $failedCount = $this->users->incrementFailedLogin($user->id);
            if ($failedCount >= self::MAX_FAILED_LOGINS) {
                $this->users->lockUntil(
                    $user->id,
                    (new DateTimeImmutable)->modify(
                        '+'.self::LOCKOUT_DURATION_SECONDS.' seconds',
                    ),
                );
            }
            $this->attempts->record(
                email: $email,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                successful: false,
                failureReason: LoginFailureReason::INVALID_CREDENTIALS,
                userId: $user->id,
            );

            return AuthOutcome::failed(LoginFailureReason::INVALID_CREDENTIALS);
        }

        // Password is valid. If the user has 2FA confirmed, the
        // caller must challenge before authenticating. Otherwise
        // the session can be established now.
        if ($user->twoFactorConfirmed) {
            return AuthOutcome::twoFactorRequired($user->id);
        }

        $this->users->resetFailedLogin($user->id);
        $this->users->recordSuccessfulLogin($user->id, $ipAddress);
        $this->attempts->record(
            email: $email,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            successful: true,
            failureReason: null,
            userId: $user->id,
        );

        return AuthOutcome::authenticated($user->id);
    }
}
