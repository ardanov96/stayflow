<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Infrastructure\Models\LoginAttempt;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * P1-02 acceptance: end-to-end login flow including the
 * no-user-enumeration property (AGENTS.md §3.3).
 *
 * The HTTP layer must be indistinguishable between "unknown email",
 * "wrong password", "locked account", and "rate-limited IP":
 *  - one generic error message ("email or password is incorrect")
 *  - one redirect back to /login
 *  - one row in login_attempts
 */
beforeEach(function (): void {
    $this->user = User::factory()->create(['password' => 'correct-horse']);
});

it('redirects to the dashboard after a successful login without 2FA', function (): void {
    $response = $this->post('/login', [
        'email' => $this->user->email,
        'password' => 'correct-horse',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->user);
    expect(LoginAttempt::query()->where('successful', true)->count())->toBe(1);
});

it('returns the same response for a wrong password and an unknown email', function (): void {
    $wrongResponse = $this->from('/login')->post('/login', [
        'email' => $this->user->email,
        'password' => 'wrong',
    ]);

    $unknownResponse = $this->from('/login')->post('/login', [
        'email' => 'nobody@example.test',
        'password' => 'any-password',
    ]);

    // Both redirect back to the login form.
    $wrongResponse->assertRedirect('/login');
    $unknownResponse->assertRedirect('/login');

    // Both have exactly one error on the email field (no
    // "unknown user" leak) and that error is the generic one.
    $wrongEmailErrors = $wrongResponse->getSession()->get('errors')->get('email') ?? [];
    $unknownEmailErrors = $unknownResponse->getSession()->get('errors')->get('email') ?? [];
    expect($wrongEmailErrors)->toHaveCount(1)
        ->and($unknownEmailErrors)->toHaveCount(1)
        ->and($wrongEmailErrors[0])->toBe($unknownEmailErrors[0]);

    // Both write one audit row.
    expect(LoginAttempt::query()->count())->toBe(2);
});

it('returns the same response for a locked account', function (): void {
    $this->user->forceFill([
        'failed_login_count' => 5,
        'locked_until' => now()->addMinutes(10),
    ])->save();

    $lockedResponse = $this->from('/login')->post('/login', [
        'email' => $this->user->email,
        'password' => 'correct-horse', // correct, but locked
    ]);

    $wrongResponse = $this->from('/login')->post('/login', [
        'email' => $this->user->email,
        'password' => 'wrong',
    ]);

    $lockedResponse->assertRedirect('/login');
    $wrongResponse->assertRedirect('/login');

    $lockedEmailErrors = $lockedResponse->getSession()->get('errors')->get('email') ?? [];
    $wrongEmailErrors = $wrongResponse->getSession()->get('errors')->get('email') ?? [];
    expect($lockedEmailErrors)->toHaveCount(1)
        ->and($wrongEmailErrors)->toHaveCount(1)
        ->and($lockedEmailErrors[0])->toBe($wrongEmailErrors[0]);
});

it('redirects to the 2FA challenge when the user has 2FA confirmed and a privileged Membership', function (): void {
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

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-horse',
    ]);

    $response->assertRedirect(route('auth.two-factor.challenge'));
    expect($response->getSession()->get('auth.two_factor_user_id'))->toBe($user->id);
    $this->assertGuest();
});

it('regenerates the session id on successful login (session-fixation defense)', function (): void {
    $this->startSession();
    $originalId = $this->app['session']->getId();

    $this->post('/login', [
        'email' => $this->user->email,
        'password' => 'correct-horse',
    ]);

    expect($this->app['session']->getId())->not->toBe($originalId);
});

it('logs out via POST and destroys the session', function (): void {
    $this->actingAs($this->user);

    $response = $this->post(route('auth.logout'));

    $response->assertRedirect(route('auth.login'));
    $this->assertGuest();
});

it('does not log out via GET (no CSRF-gap)', function (): void {
    $this->actingAs($this->user);

    $response = $this->get(route('auth.logout'));

    // The route is POST-only; GET on a POST-only route returns 405.
    // The important assertion is that the user is STILL signed in.
    $response->assertStatus(405);
    $this->assertAuthenticatedAs($this->user);
});
