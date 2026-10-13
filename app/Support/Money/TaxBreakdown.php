<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Support\Money\Exception\CurrencyMismatchException;

/**
 * Result of applying a set of {@see \App\Support\Money\TaxRule}s to a
 * subtotal. Carries the subtotal, the final total (subtotal plus
 * exclusive taxes), and a line-by-line breakdown.
 *
 * All money values in the breakdown share the subtotal's currency;
 * cross-currency rules are rejected by the calculator before they
 * reach this value object.
 */
final readonly class TaxBreakdown
{
    /**
     * @param  list<TaxLine>  $lines
     */
    public function __construct(
        public Money $subtotal,
        public Money $total,
        public array $lines,
    ) {
        if ($subtotal->currencyCode !== $total->currencyCode) {
            throw CurrencyMismatchException::between($subtotal->currencyCode, $total->currencyCode);
        }
        foreach ($lines as $line) {
            if ($line->amount->currencyCode !== $subtotal->currencyCode) {
                throw CurrencyMismatchException::between($subtotal->currencyCode, $line->amount->currencyCode);
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * Sum of all lines whose rule kind is `tax`. Returns zero when no
     * tax lines are present.
     */
    public function taxTotal(): Money
    {
        $sum = Money::zero($this->subtotal->currencyCode);

        foreach ($this->lines as $line) {
            if ($line->rule->kind === TaxKind::Tax) {
                $sum = $sum->plus($line->amount);
            }
        }

        return $sum;
    }

    /**
     * Sum of all lines whose rule kind is `service_charge`. Returns zero
     * when no service-charge lines are present.
     */
    public function serviceChargeTotal(): Money
    {
        $sum = Money::zero($this->subtotal->currencyCode);

        foreach ($this->lines as $line) {
            if ($line->rule->kind === TaxKind::ServiceCharge) {
                $sum = $sum->plus($line->amount);
            }
        }

        return $sum;
    }

    /**
     * Sum of all applied taxes and charges (both kinds). Equals the
     * total minus the subtotal (after rounding) when at least one
     * exclusive rule is present.
     */
    public function grandChargesTotal(): Money
    {
        $sum = Money::zero($this->subtotal->currencyCode);

        foreach ($this->lines as $line) {
            $sum = $sum->plus($line->amount);
        }

        return $sum;
    }
}