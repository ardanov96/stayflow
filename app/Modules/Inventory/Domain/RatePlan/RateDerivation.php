<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\RatePlan;

use App\Support\Money\Money;
use InvalidArgumentException;

final readonly class RateDerivation
{
    public function __construct(
        public DerivationType $type,
        public int $value,
    ) {
        if ($this->type === DerivationType::Percentage && ($this->value < -10000 || $this->value > 50000)) {
            throw new InvalidArgumentException("Percentage derivation basis points must be between -10000 (-100%) and 50000 (+500%), got {$this->value}.");
        }
    }

    public function apply(Money $basePrice): Money
    {
        if ($this->type === DerivationType::Percentage) {
            $effectiveFactor = 10000 + $this->value;
            if ($effectiveFactor <= 0) {
                return Money::ofMinor(0, $basePrice->currencyCode);
            }

            return $basePrice->multipliedBy($effectiveFactor)->dividedBy(10000);
        }

        // Fixed amount derivation (value in minor units)
        $deltaAmount = abs($this->value);
        $delta = Money::ofMinor($deltaAmount, $basePrice->currencyCode);

        if ($this->value >= 0) {
            return $basePrice->plus($delta);
        }

        if ($basePrice->amountMinor <= $deltaAmount) {
            return Money::ofMinor(0, $basePrice->currencyCode);
        }

        return $basePrice->minus($delta);
    }
}
