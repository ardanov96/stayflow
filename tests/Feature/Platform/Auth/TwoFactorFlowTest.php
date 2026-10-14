<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Application\Identity\Ports\TotpGenerator;
use App\Modules\Platform\Application\Identity\RegenerateRecoveryCodesAction;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

/**
 * P1-02 acceptance: 2FA enrollment, challenge, recovery codes.
 *
 * The HTTP layer must:
 *  - issue a fresh TOTP secret on /two-factor/setup begin
 *  - promote it to confirmed and return recovery codes on confirm
 *  - accept a valid TOTP code at the challenge (signing the user in)
 *  - accept a recovery code at the challenge (and consume it)
 *  - reject invalid codes
 *  - block the dashboard via the EnsureTwoFactorIsConfirmed middleware
 *    when the role requires 2FA but it is not yet confirmed
 */
beforeEach(function (): void {
    $this->user = User::factory()->create(['password' => 'correct-horse']);
    $this->totp = app(TotpGenerator::class);
    // Internal Google2FA is needed to compute the current valid code.
    $this->google2fa = new Google2FA;
});

it('enrolls 2FA: begin then confirm produces a usable TOTP and 10 recovery codes', function (): void {
    $this->actingAs($this->user);

    // 1. Begin: POST /two-factor/setup returns a secret.
    $beginResponse = $this->post(route('auth.two-factor.setup.begin'));
    $beginResponse->assertOk();
    $payload = $beginResponse->original->getData()['page']['props'];
    expect($payload['secret'] ?? null)->not->toBeNull();
    expect($payload['otpauthUrl'] ?? null)->toStartWith('otpauth://totp/');

    // 2. Confirm with the current TOTP code.
    $secret = $payload['secret'];
    $code = $this->google2fa->getCurrentOtp($secret);
    $confirmResponse = $this->post(route('auth.two-factor.setup.confirm'), [
        'code' => $code,
    ]);
    $confirmResponse->assertOk();
    $codes = $confirmResponse->original->getData()['page']['props']['codes'] ?? [];
    expect($codes)->toHaveCount(10);

    // 3. The user row is now marked confirmed and has 10 hashed recovery codes.
    $fresh = $this->user->fresh();
    expect($fresh->two_factor_confirmed_at)->not->toBeNull();
    // The `encrypted:array` cast returns the array directly on
    // read; no json_decode or decrypt() needed.
    $storedHashes = $fresh->two_factor_recovery_codes;
    expect($storedHashes)->toHaveCount(10);
});

it('rejects an invalid TOTP code at confirm', function (): void {
    $this->actingAs($this->user);
    $this->post(route('auth.two-factor.setup.begin'));

    $response = $this->post(route('auth.two-factor.setup.confirm'), [
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors('code');
    expect($this->user->fresh()->two_factor_confirmed_at)->toBeNull();
});

it('accepts a valid TOTP code at the challenge and signs the user in', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    // The `encrypted` cast on the User model decrypts the column
    // on access, so `$user->two_factor_secret` is already the
    // plaintext the gateway stored.
    $secret = (string) $user->two_factor_secret;
    $code = $this->google2fa->getCurrentOtp($secret);

    // Simulate the pending-2FA session marker set by LoginController.
    session(['auth.two_factor_user_id' => $user->id, 'auth.two_factor_remember' => false]);

    $response = $this->from(route('auth.two-factor.challenge'))->post(
        route('auth.two-factor.challenge.attempt'),
        ['code' => $code],
    );

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('accepts a recovery code at the challenge and consumes it', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    // The factory's seed code uses a non-deterministic hash that
    // we cannot reproduce in the test, so replace the codes with
    // a fresh batch whose plaintexts we control.
    $regen = app(RegenerateRecoveryCodesAction::class);
    $codes = $regen->execute($user->id);
    expect($codes)->toHaveCount(10);
    $plaintext = (string) $codes[0];

    session(['auth.two_factor_user_id' => $user->id, 'auth.two_factor_remember' => false]);

    $response = $this->from(route('auth.two-factor.challenge'))->post(
        route('auth.two-factor.challenge.attempt'),
        ['code' => $plaintext],
    );

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    // The recovery code is now consumed: only 9 remain. The
    // `encrypted:array` cast returns the array directly on read.
    $remaining = $user->fresh()->two_factor_recovery_codes;
    expect($remaining)->toHaveCount(9);
});

it('rejects a wrong TOTP code at the challenge', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    session(['auth.two_factor_user_id' => $user->id, 'auth.two_factor_remember' => false]);

    $response = $this->from(route('auth.two-factor.challenge'))->post(
        route('auth.two-factor.challenge.attempt'),
        ['code' => '000000'],
    );

    $response->assertRedirect(route('auth.two-factor.challenge'));
    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('redirects to /two-factor/setup when a privileged user lands on the dashboard without confirmed 2FA', function (): void {
    $user = User::factory()->create([
        'email' => 'owner@example.test',
    ]);
    $org = Organization::factory()->create();
    Membership::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $org->id,
        'role' => 'owner',
    ]);
    $this->actingAs($user);

    // The gated dashboard is /app (EnsureTwoFactorIsConfirmed
    // middleware). The public / route intentionally has no 2FA
    // gate so guests still see the marketing shell.
    //
    // The X-Organization-Id header activates the SetTenantContext
    // middleware (dev/non-prod path) so the BelongsToTenant
    // scope on Memberships is satisfied; without that, the
    // auth-aware resolver and the EnsureTwoFactorIsConfirmed
    // middleware both see zero Memberships (1=0 defense) and the
    // test exercises the wrong branch.
    $response = $this->withHeaders([
        'X-Organization-Id' => (string) $org->id,
    ])->get(route('app.dashboard'));

    $response->assertRedirect(route('auth.two-factor.setup'));
    expect($response->getSession()->get('status'))->not->toBeNull();
});
