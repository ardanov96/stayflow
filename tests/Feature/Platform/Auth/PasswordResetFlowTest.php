<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Infrastructure\Notifications\PasswordResetMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

/**
 * P1-02 acceptance: end-to-end password reset flow.
 *
 * Covers:
 *  1. The "forgot password" endpoint behaves identically for known
 *     and unknown emails (no enumeration, AGENTS.md §3.3).
 *  2. A valid token actually changes the password and clears the
 *     lockout / failed-login counters so a previous guessing
 *     attack does not survive a legitimate reset.
 *  3. An expired or bogus token is rejected silently (the user
 *     sees the same "your password has been reset" page either
 *     way; the password is NOT changed).
 *  4. The reset token is single-use; a second attempt with the
 *     same token returns the same generic response but does not
 *     change the password again.
 *
 * The "audit log" coverage lives in `LoginFlowTest` (login_attempts
 * table) — the password reset itself has no audit row in P1-02;
 * an AuditLog table is a P0-04 follow-up.
 */
beforeEach(function (): void {
    Mail::fake();
    $this->user = User::factory()->create([
        'email' => 'jane@example.test',
        'password' => 'old-horse-battery',
    ]);
});

it('returns the same status for a known and an unknown email on the forgot-password endpoint', function (): void {
    $knownResponse = $this->from('/forgot-password')
        ->post(route('auth.password.email'), ['email' => $this->user->email]);

    $unknownResponse = $this->from('/forgot-password')
        ->post(route('auth.password.email'), ['email' => 'ghost@example.test']);

    $knownResponse->assertRedirect('/forgot-password');
    $unknownResponse->assertRedirect('/forgot-password');

    // Same flash status — the user cannot tell which branch ran.
    expect($knownResponse->getSession()->get('status'))
        ->toBe($unknownResponse->getSession()->get('status'))
        ->toBe(__('auth.forgot.generic_success'));

    // The mailer fires once, only for the real address.
    Mail::assertQueuedCount(1);
    Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail): bool {
        return $mail->userEmail === $this->user->email
            && str_contains($mail->resetUrl, 'reset-password/')
            && str_contains($mail->resetUrl, 'email='.rawurlencode($this->user->email));
    });
});

it('renders the reset form with the token and the email prefilled from the query string', function (): void {
    $response = $this->get(route('auth.password.reset', ['token' => 'abc.def']).'?email='.rawurlencode($this->user->email));

    $response->assertOk();
    // Inertia renders the Vue page; the props arrive as JSON in
    // the response. The assertions below confirm the controller
    // handed the token through unchanged.
    expect($response->getContent())->toContain('"token":"abc.def"');
    expect($response->getContent())->toContain(rawurlencode($this->user->email));
});

it('completes a reset with a valid token: changes the password, clears lock, redirects to login', function (): void {
    // Force the user into a locked state + failed-login counter
    // so we can prove ResetPasswordAction also clears them.
    $this->user->update([
        'failed_login_count' => 4,
        'locked_until' => now()->addMinutes(10),
    ]);

    $token = Password::broker()->createToken($this->user);

    $response = $this->post(route('auth.password.store'), [
        'token' => $token,
        'email' => $this->user->email,
        'password' => 'new-strong-PASS-9',
        'password_confirmation' => 'new-strong-PASS-9',
    ]);

    $response->assertRedirect(route('auth.login'));
    expect($response->getSession()->get('status'))->toBe(__('auth.reset.success'));

    // The new password works; the old one doesn't.
    $fresh = $this->user->fresh();
    expect(Hash::check('new-strong-PASS-9', (string) $fresh->password))->toBeTrue();
    expect(Hash::check('old-horse-battery', (string) $fresh->password))->toBeFalse();

    // Lockout / counters were reset so a successful reset is not
    // penalised for prior bad attempts.
    expect($fresh->failed_login_count)->toBe(0);
    expect($fresh->locked_until)->toBeNull();
});

it('rejects an expired or unknown token silently without changing the password', function (): void {
    // Use a token that does not match any row. The broker
    // returns a non-null token for unknown emails (Laravel uses
    // a throwaway), so a syntactically valid but unmatched
    // token exercises the "valid format, no row" branch.
    $bogus = $this->post(route('auth.password.store'), [
        'token' => 'this-token-does-not-match-any-row',
        'email' => $this->user->email,
        'password' => 'still-strong-9X',
        'password_confirmation' => 'still-strong-9X',
    ]);

    // ResetPasswordAction returns null; the controller redirects
    // to the SAME login route with the same status — the
    // response is indistinguishable from the success case at
    // the UI layer (defence against token guessing).
    $bogus->assertRedirect(route('auth.login'));
    expect($bogus->getSession()->get('status'))->toBe(__('auth.reset.success'));

    // Password is unchanged.
    expect(Hash::check('old-horse-battery', (string) $this->user->fresh()->password))->toBeTrue();
    expect(Hash::check('still-strong-9X', (string) $this->user->fresh()->password))->toBeFalse();
});

it('treats a reset token as single-use: a second attempt with the same token does not change the password again', function (): void {
    $token = Password::broker()->createToken($this->user);

    $first = $this->post(route('auth.password.store'), [
        'token' => $token,
        'email' => $this->user->email,
        'password' => 'first-PASS-9X',
        'password_confirmation' => 'first-PASS-9X',
    ]);
    $first->assertRedirect(route('auth.login'));

    $second = $this->post(route('auth.password.store'), [
        'token' => $token,
        'email' => $this->user->email,
        'password' => 'second-PASS-9X',
        'password_confirmation' => 'second-PASS-9X',
    ]);

    // The UI says "your password has been reset" either way
    // (no enumeration), but the second attempt is silently
    // dropped because the broker already invalidated the token.
    $second->assertRedirect(route('auth.login'));
    expect(Hash::check('first-PASS-9X', (string) $this->user->fresh()->password))->toBeTrue();
    expect(Hash::check('second-PASS-9X', (string) $this->user->fresh()->password))->toBeFalse();
});
