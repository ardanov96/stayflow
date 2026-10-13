<?php

declare(strict_types=1);

namespace App\Support\Money;

use Brick\Math\RoundingMode;

/**
 * Tax or service-charge rule.
 *
 * Rates are stored as integer basis points (bps) so a 11 % VAT is `1100`
 * and 1 bps is 0.01 %. This keeps every rule an exact integer in the
 * database, mirrors how payment gateways transmit rate metadata, and
 * avoids float math in rules definition.
 *
 * Inclusive vs exclusive semantics:
 *   - Inclusive: the rate is already inside the subtotal (e.g. a room
     rate "IDR 1_100_000 inclusive" means the displayed price already
     contains 10 % service charge). The calculator extracts the tax
     amount for reporting; the total does not change.
 *   - Exclusive: the rate is added on top of the subtotal (or the running
     total if multiple exclusive rules are ordered). The calculator adds
     the computed amount to the total.
 *
 * `order` controls the order in which rules apply. Lower order runs first.
 * Exclusive rules accumulate; inclusive rules always extract from the
 * original subtotal (not the running total) to avoid circular logic.
 */
final readonly class TaxRule
{
    public function __construct(
        public string $name,
        public TaxKind $kind,
        public int $rateBps,
        public bool $inclusive,
        public TaxAppliesTo $appliesTo = TaxAppliesTo::All,
        public int $order = 0,
        public RoundingMode $roundingMode = RoundingMode::HALF_EVEN,
    ) {
        if ($rateBps < 0) {
            throw new \InvalidArgumentException('TaxRule rateBps cannot be negative.');
        }
        if ($rateBps > 1_000_000) {
            // 10_000 % is already nonsense for a tax; bound the input to
            // catch data-entry mistakes early.
            throw new \InvalidArgumentException('TaxRule rateBps cannot exceed 1_000_000 (10000 %).');
        }
    }

    /**
     * Return the rate as a decimal string suitable for {@see Money::multipliedBy()}.
     *
     * E.g. `rateBps = 1100` returns `"0.11000000"`. Trailing zeros are
     * harmless: brick/money parses them correctly.
     */
    public function rate(): string
    {
        return bcdiv((string) $this->rateBps, '10000', 8);
    }

    /**
     * Convenience: build an exclusive (added-on-top) rule.
     */
    public static function exclusive(
        string $name,
        int $rateBps,
        TaxKind $kind = TaxKind::Tax,
        TaxAppliesTo $appliesTo = TaxAppliesTo::All,
        int $order = 0,
    ): self {
        return new self(
            name: $name,
            kind: $kind,
            rateBps: $rateBps,
            inclusive: false,
            appliesTo: $appliesTo,
            order: $order,
        );
    }

    /**
     * Convenience: build an inclusive (already-in-the-price) rule.
     */
    public static function inclusive(
        string $name,
        int $rateBps,
        TaxKind $kind = TaxKind::Tax,
        TaxAppliesTo $appliesTo = TaxAppliesTo::All,
        int $order = 0,
    ): self {
        return new self(
            name: $name,
            kind: $kind,
            rateBps: $rateBps,
            inclusive: true,
            appliesTo: $appliesTo,
            order: $order,
        );
    }
}