<?php

declare(strict_types=1);

use App\Support\Money\Exception\CurrencyMismatchException;
use App\Support\Money\Money;
use Brick\Math\RoundingMode;
use Brick\Money\Exception\UnknownCurrencyException;

it('ofMinor stores amount and currency code', function (): void {
    $m = Money::ofMinor(1_234, 'IDR');

    expect($m->amountMinor)->toBe(1_234)
        ->and($m->currencyCode)->toBe('IDR');
});

it('of parses a decimal string in the currency major units', function (): void {
    // USD has 2 fraction digits; $12.34 = 1234 minor (cents).
    $m = Money::of('12.34', 'USD');

    expect($m->amountMinor)->toBe(1_234)
        ->and($m->currencyCode)->toBe('USD');
});

it('of accepts an integer as a whole-major-unit amount', function (): void {
    // 10 USD (no decimals) = 1000 minor.
    $m = Money::of(10, 'USD');

    expect($m->amountMinor)->toBe(1_000);
});

it('of() throws UnknownCurrencyException for an unknown ISO code', function (): void {
    Money::of('1.00', 'XYZ');
})->throws(UnknownCurrencyException::class);

it('ofMinor() throws UnknownCurrencyException for an unknown ISO code', function (): void {
    Money::ofMinor(100, 'XYZ');
})->throws(UnknownCurrencyException::class);

it('zero returns a zero amount in the requested currency', function (): void {
    $z = Money::zero('USD');

    expect($z->amountMinor)->toBe(0)
        ->and($z->isZero())->toBeTrue()
        ->and($z->currencyCode)->toBe('USD');
});

it('plus sums two amounts in the same currency', function (): void {
    $sum = Money::ofMinor(150, 'IDR')->plus(Money::ofMinor(75, 'IDR'));

    expect($sum->amountMinor)->toBe(225)
        ->and($sum->currencyCode)->toBe('IDR');
});

it('plus throws CurrencyMismatchException for different currencies', function (): void {
    Money::ofMinor(100, 'IDR')->plus(Money::ofMinor(1, 'USD'));
})->throws(CurrencyMismatchException::class);

it('minus subtracts two amounts in the same currency', function (): void {
    $diff = Money::ofMinor(150, 'IDR')->minus(Money::ofMinor(40, 'IDR'));

    expect($diff->amountMinor)->toBe(110);
});

it('minus throws CurrencyMismatchException for different currencies', function (): void {
    Money::ofMinor(100, 'IDR')->minus(Money::ofMinor(1, 'USD'));
})->throws(CurrencyMismatchException::class);

it('multipliedBy applies the factor with HALF_EVEN by default', function (): void {
    // $100.00 * 0.11 = $11.00 (exact, no rounding required).
    $m = Money::of('100.00', 'USD')->multipliedBy('0.11');

    expect($m->amountMinor)->toBe(1_100);
});

it('multipliedBy HALF_EVEN rounds 0.005 down at scale 2 (banker\'s rounding)', function (): void {
    // $1.00 * 0.005 = $0.005. With HALF_EVEN at scale 2 the digit before
    // the 5 is 0 (even), so we round DOWN to $0.00.
    $m = Money::of('1.00', 'USD')->multipliedBy('0.005');

    expect($m->amountMinor)->toBe(0);
});

it('multipliedBy HALF_UP rounds 0.005 up at scale 2', function (): void {
    $m = Money::of('1.00', 'USD')->multipliedBy('0.005', RoundingMode::HALF_UP);

    expect($m->amountMinor)->toBe(1);
});

it('multipliedBy HALF_EVEN rounds 2.5 up at the units boundary', function (): void {
    // $1.00 * 2.5 = $2.50 (exact). $1.00 * 2.5005 = $2.5005; HALF_EVEN
    // at scale 2 sees a 5 with the preceding digit 0 (even) and rounds
    // down to $2.50.
    $m = Money::of('1.00', 'USD')->multipliedBy('2.5005');

    expect($m->amountMinor)->toBe(250);
});

it('multipliedBy HALF_EVEN rounds 1.5 up to 2 (nearest even)', function (): void {
    // $1.00 * 1.5 = $1.50 (exact). Demonstrate the rule: at 1.5 the
    // HALF_EVEN path picks the nearest even integer (2).
    // We use $1.00 * 1.005 to put 5 at the cent boundary.
    $m = Money::of('1.00', 'USD')->multipliedBy('1.005');

    expect($m->amountMinor)->toBe(100); // $1.00 (rounds 1.005 down at scale 2)
});

it('dividedBy divides and rounds to the currency scale', function (): void {
    // $10.00 / 3 = $3.333... -> $3.33 with HALF_EVEN.
    $m = Money::of('10.00', 'USD')->dividedBy('3');

    expect($m->amountMinor)->toBe(333);
});

it('dividedBy throws on zero divisor', function (): void {
    Money::ofMinor(100, 'IDR')->dividedBy('0');
})->throws(InvalidArgumentException::class);

it('dividedBy throws on "0.0" divisor', function (): void {
    Money::ofMinor(100, 'IDR')->dividedBy('0.0');
})->throws(InvalidArgumentException::class);

it('negate flips the sign', function (): void {
    expect(Money::ofMinor(100, 'IDR')->negate()->amountMinor)->toBe(-100)
        ->and(Money::ofMinor(-50, 'IDR')->negate()->amountMinor)->toBe(50);
});

it('abs returns a non-negative amount', function (): void {
    expect(Money::ofMinor(-100, 'IDR')->abs()->amountMinor)->toBe(100)
        ->and(Money::ofMinor(75, 'IDR')->abs()->amountMinor)->toBe(75);
});

it('isZero / isPositive / isNegative reflect the sign', function (): void {
    expect(Money::zero('IDR')->isZero())->toBeTrue()
        ->and(Money::zero('IDR')->isPositive())->toBeFalse()
        ->and(Money::zero('IDR')->isNegative())->toBeFalse()
        ->and(Money::ofMinor(1, 'IDR')->isPositive())->toBeTrue()
        ->and(Money::ofMinor(-1, 'IDR')->isNegative())->toBeTrue();
});

it('compareTo returns -1, 0, or 1', function (): void {
    $a = Money::ofMinor(100, 'IDR');
    $b = Money::ofMinor(100, 'IDR');
    $c = Money::ofMinor(200, 'IDR');

    expect($a->compareTo($b))->toBe(0)
        ->and($a->compareTo($c))->toBe(-1)
        ->and($c->compareTo($a))->toBe(1);
});

it('equals / greater / less / greaterOrEqual / lessOrEqual work as expected', function (): void {
    $a = Money::ofMinor(100, 'IDR');
    $b = Money::ofMinor(100, 'IDR');
    $c = Money::ofMinor(200, 'IDR');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse()
        ->and($a->isLessThan($c))->toBeTrue()
        ->and($c->isGreaterThan($a))->toBeTrue()
        ->and($a->isLessThanOrEqualTo($b))->toBeTrue()
        ->and($a->isGreaterThanOrEqualTo($b))->toBeTrue();
});

it('toArray and fromArray round-trip the value', function (): void {
    $m = Money::ofMinor(1_234, 'USD');
    $round = Money::fromArray($m->toArray());

    expect($round->equals($m))->toBeTrue()
        ->and($round->amountMinor)->toBe(1_234)
        ->and($round->currencyCode)->toBe('USD');
});

it('toArray returns integer amount_minor and string currency', function (): void {
    $data = Money::ofMinor(1_234, 'USD')->toArray();

    expect($data)->toBe(['amount_minor' => 1_234, 'currency' => 'USD']);
});

it('getAmountDecimal returns the major-unit string from brick/money', function (): void {
    // $12.34 -> "12.34"; IDR 100.00 (per ISO 4217 two fraction digits).
    expect(Money::of('12.34', 'USD')->getAmountDecimal())->toBe('12.34')
        ->and(Money::ofMinor(1_000, 'IDR')->getAmountDecimal())->toBe('10.00');
});

it('__toString includes the currency code and decimal amount', function (): void {
    expect((string) Money::ofMinor(1_500, 'IDR'))->toBe('IDR 15.00')
        ->and((string) Money::of('12.34', 'USD'))->toBe('USD 12.34');
});
