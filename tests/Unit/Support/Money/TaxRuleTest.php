<?php

declare(strict_types=1);

use App\Support\Money\TaxAppliesTo;
use App\Support\Money\TaxKind;
use App\Support\Money\TaxRule;
use Brick\Math\RoundingMode;

it('rate() converts basis points to a decimal string with scale 8', function (): void {
    expect((new TaxRule('VAT', TaxKind::Tax, 1100, false))->rate())->toBe('0.11000000')
        ->and((new TaxRule('SC', TaxKind::ServiceCharge, 1000, true))->rate())->toBe('0.10000000');
});

it('rate() handles zero', function (): void {
    expect((new TaxRule('Zero', TaxKind::Tax, 0, false))->rate())->toBe('0.00000000');
});

it('rate() handles 100 % (10000 bps)', function (): void {
    expect((new TaxRule('Full', TaxKind::Tax, 10_000, false))->rate())->toBe('1.00000000');
});

it('rejects a negative rateBps', function (): void {
    new TaxRule('Bad', TaxKind::Tax, -1, false);
})->throws(InvalidArgumentException::class);

it('rejects a rateBps over 1_000_000 (10000 %)', function (): void {
    new TaxRule('Bad', TaxKind::Tax, 2_000_000, false);
})->throws(InvalidArgumentException::class);

it('exclusive() factory sets inclusive=false and the requested kind/order', function (): void {
    $r = TaxRule::exclusive('VAT', 1100, TaxKind::Tax, TaxAppliesTo::Room, order: 5);

    expect($r->name)->toBe('VAT')
        ->and($r->kind)->toBe(TaxKind::Tax)
        ->and($r->rateBps)->toBe(1100)
        ->and($r->inclusive)->toBeFalse()
        ->and($r->appliesTo)->toBe(TaxAppliesTo::Room)
        ->and($r->order)->toBe(5);
});

it('inclusive() factory sets inclusive=true', function (): void {
    $r = TaxRule::inclusive('SC', 1000, TaxKind::ServiceCharge);

    expect($r->inclusive)->toBeTrue()
        ->and($r->kind)->toBe(TaxKind::ServiceCharge)
        ->and($r->appliesTo)->toBe(TaxAppliesTo::All)
        ->and($r->order)->toBe(0);
});

it('default order is 0, default appliesTo is All, default roundingMode is HALF_EVEN', function (): void {
    $r = new TaxRule('X', TaxKind::Tax, 100, false);

    expect($r->order)->toBe(0)
        ->and($r->appliesTo)->toBe(TaxAppliesTo::All)
        ->and($r->roundingMode)->toBe(RoundingMode::HALF_EVEN);
});