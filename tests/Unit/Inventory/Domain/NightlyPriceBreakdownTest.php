<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Pricing\NightlyPriceBreakdown;
use App\Support\Money\Money;

it('calculates nightly total with base price and extra guests', function (): void {
    $basePrice = Money::ofMinor(100_000_00, 'IDR'); // 1,000,000 IDR
    $extraAdultPrice = Money::ofMinor(20_000_00, 'IDR'); // 200,000 IDR
    $extraChildPrice = Money::ofMinor(10_000_00, 'IDR'); // 100,000 IDR

    $breakdown = new NightlyPriceBreakdown(
        date: '2026-11-01',
        basePrice: $basePrice,
        extraAdults: 1,
        extraAdultPrice: $extraAdultPrice,
        extraChildren: 1,
        extraChildPrice: $extraChildPrice,
    );

    expect($breakdown->totalPrice->amountMinor)->toBe(130_000_00)
        ->and($breakdown->date)->toBe('2026-11-01')
        ->and($breakdown->extraAdults)->toBe(1)
        ->and($breakdown->extraChildren)->toBe(1);
});

it('calculates nightly total without extra guests', function (): void {
    $basePrice = Money::ofMinor(100_000_00, 'IDR');
    $zero = Money::ofMinor(0, 'IDR');

    $breakdown = new NightlyPriceBreakdown(
        date: '2026-11-01',
        basePrice: $basePrice,
        extraAdults: 0,
        extraAdultPrice: $zero,
        extraChildren: 0,
        extraChildPrice: $zero,
    );

    expect($breakdown->totalPrice->amountMinor)->toBe(100_000_00);
});
