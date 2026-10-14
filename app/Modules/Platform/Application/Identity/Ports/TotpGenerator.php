<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity\Ports;

use App\Modules\Platform\Domain\Identity\OtpWindow;
use App\Modules\Platform\Domain\Identity\TwoFactorSecret;

/**
 * Application port for TOTP code generation and verification.
 *
 * The Domain layer (TwoFactorSecret, OtpWindow) defines the *what*;
 * this port is the boundary the Application crosses when it needs to
 * actually issue, verify, or render a TOTP code. The implementation
 * lives in
 * {@see \App\Modules\Platform\Infrastructure\Identity\Adapters\Google2faTotpGenerator}
 * and is the only place in the codebase that imports
 * `Pragmarx\Google2FA\Google2FA`. Swapping the library is a
 * one-class change.
 */
interface TotpGenerator
{
    /**
     * Generate a fresh secret suitable for TOTP enrollment. The
     * returned VO has already been validated; callers may store it
     * and hand the raw string back to the user (or to a QR code).
     */
    public function generateSecret(): TwoFactorSecret;

    /**
     * Verify a 6-digit code against a stored secret, allowing
     * ±$window 30-second steps for clock skew. The verification
     * algorithm MUST be constant-time.
     */
    public function verify(TwoFactorSecret $secret, string $code, OtpWindow $window): bool;

    /**
     * Build the `otpauth://totp/...` URI that authenticator apps
     * consume when scanning a QR code. The issuer is the application
     * display name; the account label is the user-visible identifier
     * (typically the email).
     */
    public function otpauthUrl(TwoFactorSecret $secret, string $issuer, string $accountLabel): string;
}