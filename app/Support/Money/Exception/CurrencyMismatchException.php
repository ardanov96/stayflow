<?php

declare(strict_types=1);

namespace App\Support\Money\Exception;

use App\Support\Money\Money;
use DomainException;

/**
 * Thrown when an arithmetic or comparison operation is attempted between
 * two {@see Money} values in different currencies.
 *
 * Amounts in different currencies must never be summed directly;
 * convert one side through the project's currency conversion path first.
 */
final class CurrencyMismatchException extends DomainException
{
    public static function between(string $a, string $b): self
    {
        return new self(sprintf(
            'Cannot operate on Money in different currencies: %s vs %s.',
            $a,
            $b
        ));
    }
}
