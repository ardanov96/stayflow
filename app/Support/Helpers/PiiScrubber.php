<?php

declare(strict_types=1);

namespace App\Support\Helpers;

/**
 * Redacts personally identifiable information (PII) from strings or arrays.
 *
 * Used by the structured log channel so emails, phone numbers, credentials and
 * JWTs never land in log files or stdout. Conservative on purpose: false
 * positives ("***" for safe text) are better than leaks. Side-effect free, no
 * I/O, no allocation on the hot path beyond the result string.
 *
 * Two layers of scrubbing:
 *   - Value-based: regex matches on string values (message, context values).
 *     Catches emails, phone numbers, JWTs, and `key=value` / `key: value`
 *     shaped credential assignments.
 *   - Key-based: a context key whose name contains a sensitive substring
 *     (password, token, secret, api_key, cookie, etc.) has its value replaced
 *     with "***" regardless of what the value actually is, because random
 *     tokens and api keys don't look like any PII pattern.
 */
final class PiiScrubber
{
    /**
     * Context keys whose value should always be redacted regardless of the
     * value's content. Matched with str_contains (case-insensitive) so
     * `user_password` and `password_reset_token` both trigger scrubbing.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'passwd',
        'secret',
        'token',
        'api_key',
        'apikey',
        'access_token',
        'auth_token',
        'authorization',
        'bearer',
        'private_key',
        'client_secret',
        'refresh_token',
        'session',
        'cookie',
        'set-cookie',
        'credit_card',
        'card_number',
        'cvv',
    ];

    public const EMAIL_PATTERN = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';

    public const PHONE_PATTERN = '/\+?\d[\d\s\-().]{7,}\d/';

    // Matches env-file and header shapes (`api_key=secret`, `Authorization: Bearer`).
    // JSON-shaped messages (`"api_key": "secret"`) are NOT handled here because
    // a single-pass replacement cannot preserve the surrounding quotes; that
    // case is covered by the key-aware scrubbing in scrubArray() instead.
    // Allows spaces inside the value (e.g. `Authorization: Bearer abc...`)
    // but stops at newlines / structural delimiters so a single log line
    // never over-matches into the next one. JSON-shaped messages
    // (`"api_key": "secret"`) are deliberately not handled here.
    public const CREDENTIAL_PATTERN = '/(?i)\b(api[_-]?key|access[_-]?token|auth[_-]?token|secret|password|passwd|token|bearer|authorization)\b\s*[:=]\s*[^,;"\'<>&\x00-\x1F}\n]+/';

    public const JWT_PATTERN = '/eyJ[A-Za-z0-9_\-]+\.eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/';

    public static function scrubString(string $value): string
    {
        $value = (string) preg_replace(self::CREDENTIAL_PATTERN, '$1=***', $value);
        $value = (string) preg_replace(self::JWT_PATTERN, '***.***', $value);
        $value = (string) preg_replace(self::EMAIL_PATTERN, '***@***.***', $value);
        $value = (string) preg_replace(self::PHONE_PATTERN, '+***********', $value);

        return $value;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    public static function scrubArray(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $out[$key] = '***';

                continue;
            }
            $out[$key] = match (true) {
                is_string($value) => self::scrubString($value),
                is_array($value) => self::scrubArray($value),
                default => $value,
            };
        }

        return $out;
    }

    /**
     * @param  string|array<mixed>  $value
     * @return string|array<mixed>
     */
    public static function scrub(string|array $value): string|array
    {
        return is_string($value) ? self::scrubString($value) : self::scrubArray($value);
    }

    private static function isSensitiveKey(string $key): bool
    {
        $lowered = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $name) {
            if (str_contains($lowered, $name)) {
                return true;
            }
        }

        return false;
    }
}
