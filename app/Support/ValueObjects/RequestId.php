<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * Per-request correlation id.
 *
 * Read from the inbound X-Request-Id header (if it looks safe) or freshly
 * generated. Used as the primary key for joining logs, Sentry events, and
 * downstream queue jobs of the same logical request.
 */
final readonly class RequestId implements Stringable
{
    public const HEADER = 'X-Request-Id';

    public function __construct(public string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('RequestId cannot be empty.');
        }
    }

    public static function generate(): self
    {
        // 16 hex chars (8 random bytes) — short, sortable-friendly, collision-safe
        // for any realistic request rate.
        return new self(bin2hex(random_bytes(8)));
    }

    public static function fromHeader(?string $header): self
    {
        $header = $header !== null ? trim($header) : '';

        // Only accept safe characters; reject anything that could be a log-injection
        // vector (newlines, control chars, html, quotes) and cap length.
        if ($header !== '' && preg_match('/\A[A-Za-z0-9_\-]{1,64}\z/', $header) === 1) {
            return new self($header);
        }

        return self::generate();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
