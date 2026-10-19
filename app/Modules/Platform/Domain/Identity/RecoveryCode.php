<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Identity;

use InvalidArgumentException;

/**
 * Immutable value object for a one-time recovery code.
 *
 * Recovery codes are the fallback path when a user loses access to
 * their TOTP authenticator. Each code:
 *
 *  - is 10 chars, drawn from a Crockford-style alphabet that avoids
 *    visually ambiguous glyphs (no I, L, O, 0, 1);
 *  - is shown in plaintext to the user exactly once (on enrollment or
 *    regeneration), then stored only as a hash (Argon2id) for later
 *    verification;
 *  - is single-use: after a successful match the gateway removes the
 *    hash from the user's recovery-codes list, so a second use of the
 *    same code is rejected.
 *
 * The hashing is intentionally done here (a small wrapper around
 * Laravel's `Hash` facade would have to be injected otherwise) — the
 * Domain owns the policy "recovery codes are never stored plaintext"
 * and uses `password_hash(..., PASSWORD_ARGON2ID)` directly. Argon2id
 * is the PHP-recommended default for password storage since 8.2 and
 * is the strongest available option in stock PHP.
 */
final readonly class RecoveryCode
{
    public const LENGTH = 10;

    /** Crockford-style: removes 0/1/I/L/O to avoid copy-paste mistakes. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(public string $value)
    {
        if (preg_match('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{'.self::LENGTH.'}$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Recovery code must be exactly %d chars from the alphabet %s.',
                self::LENGTH,
                self::ALPHABET,
            ));
        }
    }

    /**
     * Cryptographically-strong random generator. Uses random_int, not
     * Str::random, so the entropy is the full alphabet (Str::random
     * uses a 62-char set with a 6-bit-per-char bias).
     */
    public static function generate(): self
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return new self($out);
    }

    /**
     * Return the Argon2id hash of this code, suitable for storage.
     */
    public function hash(): string
    {
        return password_hash($this->value, PASSWORD_ARGON2ID);
    }

    /**
     * Constant-time verification against a stored hash. Returns
     * `false` for any mismatch (including malformed input) and never
     * leaks timing information.
     */
    public static function verifyAgainst(string $input, string $storedHash): bool
    {
        if ($storedHash === '') {
            return false;
        }

        return password_verify($input, $storedHash);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
