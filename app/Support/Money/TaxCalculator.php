<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Support\Money\Exception\CurrencyMismatchException;

/**
 * Applies a set of {@see \App\Support\Money\TaxRule}s to a money subtotal
 * and returns a {@see \App\Support\Money\TaxBreakdown}.
 *
 * Algorithm:
 *  1. Sort rules by `order` ascending; ties keep insertion order.
 *  2. For each rule in order:
 *     - Inclusive: extract the tax amount from the original subtotal
 *       (tax = subtotal minus subtotal divided by (1 + rate)). The total
 *       does not change because the rate is already in the subtotal.
 *     - Exclusive: compute the tax on the running total
 *       (tax = runningTotal times rate) and add it to the running total.
 *  3. The breakdown total is the final running total. The breakdown
 *     subtotal is the original input. `lines` records each applied rule.
 *
 * Inclusive rules are always computed against the original subtotal (not
 * the running total) to avoid circular logic when multiple inclusive
 * rules share a price. This is the conventional hospitality model where
 * a "rate includes service charge and VAT" quote splits both out of the
 * displayed price independently.
 */
final class TaxCalculator
{
    public function apply(Money $subtotal, TaxRule ...$rules): TaxBreakdown
    {
        $sorted = $this->sortByOrder($rules);
        $runningTotal = $subtotal;
        $lines = [];

        foreach ($sorted as $rule) {
            if ($rule->inclusive) {
                $tax = $this->extractInclusive($subtotal, $rule);
                $lines[] = new TaxLine($rule, $tax, inclusive: true);
                // Running total unchanged: the tax is already inside the subtotal.
            } else {
                $tax = $this->computeExclusive($runningTotal, $rule);
                $lines[] = new TaxLine($rule, $tax, inclusive: false);
                $runningTotal = $runningTotal->plus($tax);
            }
        }

        return new TaxBreakdown(
            subtotal: $subtotal,
            total: $runningTotal,
            lines: $lines,
        );
    }

    private function computeExclusive(Money $amount, TaxRule $rule): Money
    {
        return $amount->multipliedBy($rule->rate(), $rule->roundingMode);
    }

    private function extractInclusive(Money $subtotal, TaxRule $rule): Money
    {
        // net = subtotal / (1 + rate); tax = subtotal - net
        $onePlusRate = bcadd('1', $rule->rate(), 8);
        $net = $subtotal->dividedBy($onePlusRate, $rule->roundingMode);

        return $subtotal->minus($net);
    }

    /**
     * @param  list<TaxRule>  $rules
     * @return list<TaxRule>
     */
    private function sortByOrder(array $rules): array
    {
        $copy = $rules;
        usort($copy, static fn (TaxRule $a, TaxRule $b): int => $a->order <=> $b->order);

        return $copy;
    }
}