<?php

declare(strict_types=1);

namespace App\Support\Helpers;

/**
 * Redacts personally identifiable information (PII) from strings or arrays.
 *
 * Used by the structured log channel so emails, phone numbers, and credentials
 * never land in log files or stdout. Conservative on purpose: false positives
 * ("***" for safe text) are better than leaks. Side-effect free, no I/O.
 *
 * Detects:
 *   - Email addresses           (local@domain.tld)
 *   - Phone numbers             (8+ consecutive digits, optionally +prefix)
 *   - Credential assignments    ("api_key=val", "password: val", "Bearer ...")
 *   - JWTs                      (three base64url segments separated by dots)
 */
final class PiiScrubber
{
    public const EMAIL_PATTERN = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';

    public const PHONE_PATTERN = '/\+?\d[\d\s\-().]{7,}\d/';

    public const CREDENTIAL_PATTERN = '/(?i)\b(api[_-]?key|access[_-]?token|auth[_-]?token|secret|password|passwd|token|bearer|authorization)\b\s*[:=]\s*[^\s,;"\'<>&\x00-\x1F]+/';

    public const JWT_PATTERN = '/eyJ[A-Za-z0-9_\-]+\.eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/';

    public static function scrubString(string $value): string
    {
        $value = (string) preg_replace(self::JWT_PATTERN, '***.***', $value);
        $value = (string) preg_replace(self::CREDENTIAL_PATTERN, '$1=***', $value);
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
}
