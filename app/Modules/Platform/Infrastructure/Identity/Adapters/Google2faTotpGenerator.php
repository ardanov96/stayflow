<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Identity\Adapters;

use App\Modules\Platform\Application\Identity\Ports\TotpGenerator;
use App\Modules\Platform\Domain\Identity\OtpWindow;
use App\Modules\Platform\Domain\Identity\TwoFactorSecret;
use PragmaRX\Google2FA\Google2FA;

/**
 * Thin adapter that delegates every TOTP call to pragmarx/google2fa
 * v9.x. The library is the only place that imports
 * `PragmaRX\Google2FA\Google2FA`; swapping to `spomky-labs/otphp`
 * (or to a hand-rolled HMAC-SHA1 implementation, if we ever need to
 * drop the dependency) is a one-class change behind the
 * {@see TotpGenerator} port.
 *
 * The Google2FA instance is per-request (created in the constructor
 * via a new); it has no state besides the configured window, so
 * the cost is negligible. If a future need appears, the binding
 * can be moved to a singleton in PlatformServiceProvider.
 */
final class Google2faTotpGenerator implements TotpGenerator
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    public function generateSecret(): TwoFactorSecret
    {
        // v9 default: 32 chars (160 bits). We accept whatever
        // Google2FA returns; TwoFactorSecret's constructor enforces
        // the MIN_LENGTH floor so a regression in the lib (e.g. a
        // future default drop) is caught at the Domain boundary.
        $secret = $this->google2fa->generateSecretKey();

        return new TwoFactorSecret($secret);
    }

    public function verify(TwoFactorSecret $secret, string $code, OtpWindow $window): bool
    {
        $normalized = preg_replace('/\s+/', '', $code);
        if (! is_string($normalized) || $normalized === '') {
            return false;
        }

        return $this->google2fa->verifyKey(
            $secret->value,
            $normalized,
            $window->steps,
        );
    }

    public function otpauthUrl(TwoFactorSecret $secret, string $issuer, string $accountLabel): string
    {
        // google2fa's `getQRCodeUrl` used to hit the (now-off)
        // Google Charts API and is not what we want — we want the
        // `otpauth://` URI that authenticator apps consume. The
        // Google2FA::getQRCodeUrl signature is "issuer + holder +
        // secret", but it returns a https://chart.googleapis.com
        // URL. We hand-build the otpauth URL instead.
        $params = http_build_query([
            'secret' => $secret->value,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);
        $label = rawurlencode($issuer.':'.$accountLabel);

        return "otpauth://totp/{$label}?{$params}";
    }
}
