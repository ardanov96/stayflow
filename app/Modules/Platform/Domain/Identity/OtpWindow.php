<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Identity;

use InvalidArgumentException;

/**
 * Clock-skew window for TOTP verification, expressed in 30-second
 * steps. A window of 1 accepts the current step and the previous
 * one (and the next one), which is the standard for "lenient mode"
 * (RFC 6238 §5.2 calls this the "delay window"). A window of 0 is
 * strict: only the current step.
 *
 * Used by {@see \App\Modules\Platform\Application\Identity\Ports\TotpGenerator::verify()}.
 */
final readonly class OtpWindow
{
    public const STRICT = 0;
    public const LENIENT = 1;
    public const MAX = 10;

    public function __construct(public int $steps)
    {
        if ($steps < 0 || $steps > self::MAX) {
            throw new InvalidArgumentException(sprintf(
                'OtpWindow::steps must be in [0, %d]; got %d.',
                self::MAX,
                $steps,
            ));
        }
    }

    public static function strict(): self
    {
        return new self(self::STRICT);
    }

    public static function lenient(): self
    {
        return new self(self::LENIENT);
    }
}