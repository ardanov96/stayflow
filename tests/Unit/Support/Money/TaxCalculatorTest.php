<?php

declare(strict_types=1);

use App\Support\Money\Money;
use App\Support\Money\TaxCalculator;
use App\Support\Money\TaxKind;
use App\Support\Money\TaxRule;
use Brick\Math\RoundingMode;

beforeEach(function (): void {
    $this->calc = new TaxCalculator;
});

it('with no rules returns an empty breakdown whose total equals the subtotal', function (): void {
    $b = $this->calc->apply(Money::ofMinor(100_000, 'IDR'));

    expect($b->isEmpty())->toBeTrue()
        ->and($b->lines)->toBe([])
        ->and($b->total->equals($b->subtotal))->toBeTrue();
});

it('applies a single exclusive tax on top of the subtotal', function (): void {
    // USD 100.00 + 11 % VAT = USD 111.00.
    $b = $this->calc->apply(
        Money::of('100.00', 'USD'),
        TaxRule::exclusive('VAT', 1100, TaxKind::Tax)
    );

    expect($b->total->getAmountDecimal())->toBe('111.00')
        ->and($b->taxTotal()->getAmountDecimal())->toBe('11.00')
        ->and($b->serviceChargeTotal()->amountMinor)->toBe(0);
});

it('applies a single inclusive tax without changing the total', function (): void {
    // USD 110.00 inclusive of 10 % service charge:
    //   net = 110 / 1.10 = 100.00
    //   sc = 110 - 100.00 = 10.00
    //   total = 110.00 (no change)
    $b = $this->calc->apply(
        Money::of('110.00', 'USD'),
        TaxRule::inclusive('SC', 1000, TaxKind::ServiceCharge)
    );

    expect($b->total->getAmountDecimal())->toBe('110.00')
        ->and($b->subtotal->getAmountDecimal())->toBe('110.00')
        ->and($b->serviceChargeTotal()->getAmountDecimal())->toBe('10.00')
        ->and($b->lines[0]->inclusive)->toBeTrue();
});

it('chains exclusive rules in order: SC 10% then VAT 11% on USD 100 = USD 122.10', function (): void {
    $b = $this->calc->apply(
        Money::of('100.00', 'USD'),
        TaxRule::exclusive('VAT', 1100, TaxKind::Tax, order: 1),
        TaxRule::exclusive('SC', 1000, TaxKind::ServiceCharge, order: 0),
    );

    // SC first: 100 + 10 = 110, then VAT on 110 = 12.10, total = 122.10.
    expect($b->total->getAmountDecimal())->toBe('122.10')
        ->and($b->serviceChargeTotal()->getAmountDecimal())->toBe('10.00')
        ->and($b->taxTotal()->getAmountDecimal())->toBe('12.10')
        ->and($b->lines[0]->rule->name)->toBe('SC')  // order respected
        ->and($b->lines[1]->rule->name)->toBe('VAT');
});

it('combines inclusive and exclusive rules', function (): void {
    // USD 110.00 inclusive of 11 % VAT, plus 10 % exclusive service
    // charge on the running total (the inclusive line does not change it).
    //
    // VAT in 110: 1 + 0.11 = 1.11; net = 110 / 1.11 = 99.099... -> 99.10;
    // VAT = 110 - 99.10 = 10.90.
    // SC on 110: 110 * 0.10 = 11.00.
    // Total: 110 + 11 = 121.00.
    $b = $this->calc->apply(
        Money::of('110.00', 'USD'),
        TaxRule::inclusive('VAT', 1100, TaxKind::Tax, order: 0),
        TaxRule::exclusive('SC', 1000, TaxKind::ServiceCharge, order: 1),
    );

    expect($b->taxTotal()->getAmountDecimal())->toBe('10.90')
        ->and($b->serviceChargeTotal()->getAmountDecimal())->toBe('11.00')
        ->and($b->total->getAmountDecimal())->toBe('121.00');
});

it('handles a zero tax rate as a no-op', function (): void {
    $b = $this->calc->apply(
        Money::ofMinor(100_000, 'IDR'),
        TaxRule::exclusive('Zero', 0, TaxKind::Tax)
    );

    expect($b->total->amountMinor)->toBe(100_000)
        ->and($b->taxTotal()->amountMinor)->toBe(0);
});

it('applies IDR with the ISO 4217 two-fraction-digit scale', function (): void {
    // 1_000.00 IDR + 10 % PB1 = 1_100.00 IDR (110_000 minor).
    $b = $this->calc->apply(
        Money::ofMinor(100_000, 'IDR'),
        TaxRule::exclusive('PB1', 1000, TaxKind::Tax)
    );

    expect($b->total->amountMinor)->toBe(110_000)
        ->and($b->taxTotal()->amountMinor)->toBe(10_000);
});

it('grandChargesTotal() equals total minus subtotal for exclusive-only rules', function (): void {
    $b = $this->calc->apply(
        Money::of('100.00', 'USD'),
        TaxRule::exclusive('VAT', 1100, TaxKind::Tax),
        TaxRule::exclusive('SC', 500, TaxKind::ServiceCharge),
    );

    // 100 + 11 + 5.5 (HALF_EVEN) = 116.55
    expect($b->total->getAmountDecimal())->toBe('116.55')
        ->and($b->grandChargesTotal()->getAmountDecimal())->toBe('16.55');
});

it('taxTotal() and serviceChargeTotal() split lines by kind', function (): void {
    // USD 100.00 + 10 % PB1 (tax) + 5 % SC (service charge).
    // PB1 first (order 0, insertion first): 100 * 0.10 = 10.00.
    // Running total = 110.00; SC = 110 * 0.05 = 5.50.
    $b = $this->calc->apply(
        Money::of('100.00', 'USD'),
        TaxRule::exclusive('PB1', 1000, TaxKind::Tax),
        TaxRule::exclusive('SC', 500, TaxKind::ServiceCharge),
    );

    expect($b->taxTotal()->getAmountDecimal())->toBe('10.00')
        ->and($b->serviceChargeTotal()->getAmountDecimal())->toBe('5.50');
});

it('preserves order across mixed rules (insertion order kept on ties)', function (): void {
    $b = $this->calc->apply(
        Money::of('100.00', 'USD'),
        TaxRule::exclusive('A', 100, TaxKind::Tax, order: 1),
        TaxRule::exclusive('B', 200, TaxKind::ServiceCharge, order: 0),
        TaxRule::exclusive('C', 300, TaxKind::Tax, order: 1),  // same order as A
    );

    // B first (order 0), then A and C in insertion order (order 1).
    expect($b->lines[0]->rule->name)->toBe('B')
        ->and($b->lines[1]->rule->name)->toBe('A')
        ->and($b->lines[2]->rule->name)->toBe('C');
});

it('respects the per-rule rounding mode (HALF_EVEN vs HALF_UP at 0.005)', function (): void {
    // $1.00 * 0.5 % service charge = $0.005.
    // HALF_EVEN at scale 2 -> $0.00 (preceding digit is 0, even, round down).
    // HALF_UP at scale 2    -> $0.01.
    $even = $this->calc->apply(
        Money::of('1.00', 'USD'),
        new TaxRule('SC', TaxKind::ServiceCharge, 50, false, roundingMode: RoundingMode::HALF_EVEN),
    );
    $up = $this->calc->apply(
        Money::of('1.00', 'USD'),
        new TaxRule('SC', TaxKind::ServiceCharge, 50, false, roundingMode: RoundingMode::HALF_UP),
    );

    expect($even->serviceChargeTotal()->amountMinor)->toBe(0)
        ->and($up->serviceChargeTotal()->amountMinor)->toBe(1);
});
