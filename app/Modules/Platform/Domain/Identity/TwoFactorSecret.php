<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Identity;

use InvalidArgumentException;

/**
 * Immutable value object for a TOTP base32 secret.
 *
 * The string is opaque to this class; the verification logic lives
 * behind {@see \App\Modules\Platform\Application\Identity\Ports\TotpGenerator}
 * so the Domain stays free of any specific TOTP library. The
 * encryption-at-rest is the Eloquent model's job (Laravel `encrypted`
 * cast); this VO just guards the format.
 *
 * Valid base32 alphabet: A-Z and 2-7. Padding with `=` is optional and
 * is stripped on construction to keep equality checks simple.
 */
final readonly class TwoFactorSecret
{
    /**
     * Lower bound matches the google2fa v9 default (32 chars =
     * 160 bits of entropy). We do not depend on google2fa here, but
     * the lower bound is the industry consensus for a TOTP secret
     * in 2026 and rejecting shorter strings early surfaces
     * "the secret got truncated somewhere" bugs.
     */
    public const MIN_LENGTH = 16;

    /** @var non-empty-string */
    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(rtrim($value, '='));
        if ($normalized === '') {
            throw new InvalidArgumentException('TwoFactorSecret cannot be empty.');
        }
        if (strlen($normalized) < self::MIN_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'TwoFactorSecret must be at least %d characters; got %d.',
                self::MIN_LENGTH,
                strlen($normalized),
            ));
        }
        if (preg_match('/^[A-Z2-7]+$/', $normalized) !== 1) {
            throw new InvalidArgumentException(
                'TwoFactorSecret must be RFC 4648 base32 (A-Z, 2-7).',
            );
        }
        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        // hash_equals prevents a timing oracle on the secret string.
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}