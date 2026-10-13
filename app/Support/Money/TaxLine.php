<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * One applied tax on a {@see TaxBreakdown}.
 *
 * `inclusive = true` means the amount is already inside the subtotal
 * (extracted for reporting only). `inclusive = false` means the amount
 * is added on top of the subtotal to reach the total.
 */
final readonly class TaxLine
{
    public function __construct(
        public TaxRule $rule,
        public Money $amount,
        public bool $inclusive,
    ) {}
}
