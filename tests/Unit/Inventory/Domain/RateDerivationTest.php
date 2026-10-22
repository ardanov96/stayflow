<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\RatePlan\DerivationType;
use App\Modules\Inventory\Domain\RatePlan\RateDerivation;
use App\Support\Money\Money;

it('derives price with percentage discount', function (): void {
    // -10.00% (1000 basis points discount)
    $derivation = new RateDerivation(DerivationType::Percentage, -1000);

    $base = Money::of('100.00', 'USD');
    $derived = $derivation->apply($base);

    expect($derived->amountMinor)->toBe(9000)
        ->and($derived->currencyCode)->toBe('USD');
});

it('derives price with percentage premium', function (): void {
    // +15.50% (1550 basis points premium)
    $derivation = new RateDerivation(DerivationType::Percentage, 1550);

    $base = Money::of('100.00', 'USD');
    $derived = $derivation->apply($base);

    expect($derived->amountMinor)->toBe(11550)
        ->and($derived->currencyCode)->toBe('USD');
});

it('derives price with fixed amount discount', function (): void {
    // -50,000 IDR (in minor units: 50,000.00 = 5_000_000 minor)
    $derivation = new RateDerivation(DerivationType::FixedAmount, -5_000_000);

    $base = Money::ofMinor(100_000_000, 'IDR'); // 1,000,000 IDR
    $derived = $derivation->apply($base);

    expect($derived->amountMinor)->toBe(95_000_000)
        ->and($derived->currencyCode)->toBe('IDR');
});

it('clamps derived price to zero when discount exceeds base', function (): void {
    $derivation = new RateDerivation(DerivationType::FixedAmount, -150_000_000);

    $base = Money::ofMinor(100_000_000, 'IDR');
    $derived = $derivation->apply($base);

    expect($derived->amountMinor)->toBe(0)
        ->and($derived->currencyCode)->toBe('IDR');
});

it('rejects percentage derivation outside valid bounds', function (): void {
    expect(fn () => new RateDerivation(DerivationType::Percentage, -15000))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new RateDerivation(DerivationType::Percentage, 60000))
        ->toThrow(InvalidArgumentException::class);
});
