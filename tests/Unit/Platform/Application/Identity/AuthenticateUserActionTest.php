<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Application\Identity\AuthenticateUserAction;
use App\Modules\Platform\Application\Identity\AuthOutcomeStatus;
use App\Modules\Platform\Application\Identity\Ports\LoginAttemptRecorder;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Domain\Identity\LoginFailureReason;
use App\Modules\Platform\Infrastructure\Models\LoginAttempt;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * P1-02 acceptance: lockout, throttle, no user enumeration.
 *
 * The action is the only gate that decides whether a login attempt
 * is allowed. These tests pin the security contract (AGENTS.md §3
 * login flow) so any future change to {@see AuthenticateUserAction}
 * that weakens it is caught by CI.
 */
beforeEach(function (): void {
    $this->users = app(UserIdentityGateway::class);
    $this->attempts = app(LoginAttemptRecorder::class);
    $this->action = new AuthenticateUserAction($this->users, $this->attempts);
});

it('authenticates a known user with the right password and records a successful attempt', function (): void {
    $user = User::factory()->create(['password' => 'correct-horse']);

    $outcome = $this->action->execute($user->email, 'correct-horse', '203.0.113.1', 'PHPUnit');

    expect($outcome->status)->toBe(AuthOutcomeStatus::AUTHENTICATED)
        ->and($outcome->userId)->toBe($user->id)
        ->and(LoginAttempt::query()->where('successful', true)->count())->toBe(1);
});

it('returns Failed/InvalidCredentials for a wrong password and increments the failed counter', function (): void {
    $user = User::factory()->create(['password' => 'correct-horse']);

    $outcome = $this->action->execute($user->email, 'wrong-password', '203.0.113.1', 'PHPUnit');

    expect($outcome->status)->toBe(AuthOutcomeStatus::FAILED)
        ->and($outcome->failureReason)->toBe(LoginFailureReason::INVALID_CREDENTIALS)
        ->and($user->fresh()->failed_login_count)->toBe(1)
        ->and(LoginAttempt::query()->where('successful', false)->count())->toBe(1);
});

it('locks the account after five consecutive failed attempts', function (): void {
    $user = User::factory()->create(['password' => 'correct-horse']);

    for ($i = 0; $i < 5; $i++) {
        $this->action->execute($user->email, "wrong-$i", '203.0.113.1', 'PHPUnit');
    }

    $fresh = $user->fresh();
    expect($fresh->failed_login_count)->toBe(5)
        ->and($fresh->locked_until)->not->toBeNull()
        ->and($fresh->locked_until->isFuture())->toBeTrue();

    // A sixth attempt with the RIGHT password still fails because
    // the account is locked.
    $outcome = $this->action->execute($user->email, 'correct-horse', '203.0.113.1', 'PHPUnit');
    expect($outcome->status)->toBe(AuthOutcomeStatus::FAILED)
        ->and($outcome->failureReason)->toBe(LoginFailureReason::ACCOUNT_LOCKED);
});

it('returns Failed/InvalidCredentials for an unknown email without revealing the email is unknown', function (): void {
    $outcome = $this->action->execute('nobody@example.test', 'any-password', '203.0.113.1', 'PHPUnit');

    expect($outcome->status)->toBe(AuthOutcomeStatus::FAILED)
        ->and($outcome->failureReason)->toBe(LoginFailureReason::INVALID_CREDENTIALS)
        ->and($outcome->userId)->toBeNull();

    // The unknown-email branch still writes exactly one audit row
    // so ops dashboards are not blind to scanning activity.
    expect(LoginAttempt::query()->count())->toBe(1)
        ->and(LoginAttempt::query()->first()->email)->toBe('nobody@example.test');
});

it('rate-limits by IP when 20 failures hit the same address in 60s', function (): void {
    $user = User::factory()->create(['password' => 'correct-horse']);

    // 20 prior failures from the IP, all distinct emails so the
    // per-account counter is not the trigger.
    for ($i = 0; $i < 20; $i++) {
        LoginAttempt::query()->create([
            'email' => "victim-{$i}@example.test",
            'ip_address' => '198.51.100.7',
            'user_agent' => 'PHPUnit',
            'successful' => false,
            'failure_reason' => LoginFailureReason::INVALID_CREDENTIALS->value,
            'user_id' => null,
            'attempted_at' => now(),
        ]);
    }

    $outcome = $this->action->execute($user->email, 'correct-horse', '198.51.100.7', 'PHPUnit');

    expect($outcome->status)->toBe(AuthOutcomeStatus::FAILED)
        ->and($outcome->failureReason)->toBe(LoginFailureReason::RATE_LIMITED);
    // userId is intentionally not exposed in the Failed outcome (no
    // enumeration); the audit row in login_attempts does carry it.
    $audit = LoginAttempt::query()
        ->where('ip_address', '198.51.100.7')
        ->where('failure_reason', LoginFailureReason::RATE_LIMITED->value)
        ->first();
    expect($audit?->user_id)->toBe($user->id);
});

it('returns TwoFactorRequired when the user has confirmed 2FA and a privileged Membership', function (): void {
    $user = User::factory()->withTwoFactor()->create([
        'password' => 'correct-horse',
        'email' => 'owner@example.test',
    ]);
    $org = Organization::factory()->create();
    Membership::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $org->id,
        'role' => 'owner',
    ]);

    $outcome = $this->action->execute($user->email, 'correct-horse', '203.0.113.1', 'PHPUnit');

    expect($outcome->status)->toBe(AuthOutcomeStatus::TWO_FACTOR_REQUIRED)
        ->and($outcome->userId)->toBe($user->id);
});

it('resets the failed-login counter after a successful authentication', function (): void {
    $user = User::factory()->create([
        'password' => 'correct-horse',
        'failed_login_count' => 2,
    ]);

    $this->action->execute($user->email, 'correct-horse', '203.0.113.1', 'PHPUnit');

    expect($user->fresh()->failed_login_count)->toBe(0);
});

it('records a single attempt per call regardless of branch', function (): void {
    $user = User::factory()->create(['password' => 'correct-horse']);

    $this->action->execute($user->email, 'wrong', '203.0.113.1', 'PHPUnit');
    $this->action->execute($user->email, 'correct-horse', '203.0.113.1', 'PHPUnit');
    $this->action->execute('nobody@example.test', 'whatever', '203.0.113.1', 'PHPUnit');

    expect(LoginAttempt::query()->count())->toBe(3);
});
