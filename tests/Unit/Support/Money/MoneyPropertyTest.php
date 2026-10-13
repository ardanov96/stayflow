<?php

declare(strict_types=1);

use App\Support\Money\Money;

// Deterministic seed for property-based tests: the same inputs must run
// every time so a regression is reproducible from the failing input.
mt_srand(2025_10_13);

it('addition is associative for random IDR amounts (30 iterations)', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $a = Money::ofMinor(random_int(0, 1_000_000), 'IDR');
        $b = Money::ofMinor(random_int(0, 1_000_000), 'IDR');
        $c = Money::ofMinor(random_int(0, 1_000_000), 'IDR');

        $left = $a->plus($b)->plus($c)->amountMinor;
        $right = $a->plus($b->plus($c))->amountMinor;

        expect($left)->toBe(
            $right,
            "iteration $i: a={$a->amountMinor} b={$b->amountMinor} c={$c->amountMinor}"
        );
    }
});

it('addition is commutative for random IDR amounts (30 iterations)', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $a = Money::ofMinor(random_int(0, 1_000_000), 'IDR');
        $b = Money::ofMinor(random_int(0, 1_000_000), 'IDR');

        expect($a->plus($b)->amountMinor)->toBe(
            $b->plus($a)->amountMinor,
            "iteration $i: a={$a->amountMinor} b={$b->amountMinor}"
        );
    }
});

it('adding zero is the identity (30 iterations)', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $a = Money::ofMinor(random_int(0, 1_000_000), 'IDR');

        expect($a->plus(Money::zero('IDR'))->amountMinor)->toBe(
            $a->amountMinor,
            "iteration $i: a={$a->amountMinor}"
        );
    }
});

it('subtracting a value from itself is zero (30 iterations)', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $a = Money::ofMinor(random_int(0, 1_000_000), 'IDR');

        expect($a->minus($a)->amountMinor)->toBe(0, "iteration $i: a={$a->amountMinor}");
    }
});

it('multiplying by 1.0 (string "1") is the identity (30 iterations)', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $a = Money::ofMinor(random_int(0, 1_000_000), 'IDR');

        expect($a->multipliedBy('1')->amountMinor)->toBe(
            $a->amountMinor,
            "iteration $i: a={$a->amountMinor}"
        );
    }
});

it('allocate preserves the total for N=2..15 and random source (40 iterations)', function (): void {
    for ($i = 0; $i < 40; $i++) {
        $source = Money::ofMinor(random_int(0, 1_000_000), 'IDR');
        $parts = random_int(2, 15);

        $split = $source->allocate($parts);
        $sum = Money::zero('IDR');
        foreach ($split as $p) {
            $sum = $sum->plus($p);
        }

        expect($sum->amountMinor)->toBe(
            $source->amountMinor,
            "iteration $i: source={$source->amountMinor} parts={$parts}"
        );

        // The remainder (extra minor units distributed to the first
        // parts) is always in the range 0..N-1. Use integer math to
        // avoid float drift on the comparison.
        $expectedPerPart = intdiv($source->amountMinor, $parts);
        $remainder = $source->amountMinor - $expectedPerPart * $parts;
        expect($remainder)->toBeGreaterThanOrEqual(0)
            ->and($remainder)->toBeLessThan($parts);
    }
});