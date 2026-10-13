<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Support\Money\Exception\CurrencyMismatchException;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Currency as BrickCurrency;
use Brick\Money\Money as BrickMoney;
use InvalidArgumentException;
use Stringable;

/**
 * Money value object.
 *
 * Every amount is stored as an integer in the currency's minor units
 * (e.g. cents for USD; for IDR the major unit IS the minor unit).
 * The {@see \Brick\Money\Currency} ISO 4217 code accompanies every value.
 *
 * Rounding default is HALF_EVEN (banker's rounding). Callers may pass
 * a different {@see \Brick\Math\RoundingMode} per operation when the
 * domain requires it (e.g. tax on cash payments may use HALF_UP per
 * local regulation).
 *
 * Float amounts are rejected at the type level: construct from an integer
 * minor count ({@see self::ofMinor()}) or a decimal string
 * ({@see self::of()}). Allocation is exact (no rounding loss): the sum of
 * the parts always equals the source.
 *
 * See docs/MONEY.md for the project's full money policy.
 */
final readonly class Money implements Stringable
{
    public const DEFAULT_ROUNDING_MODE = RoundingMode::HALF_EVEN;

    /**
     * @param  int  $amountMinor  amount in the currency's minor units
     * @param  string  $currencyCode  ISO 4217 code (e.g. "IDR", "USD")
     */
    private function __construct(
        public int $amountMinor,
        public string $currencyCode,
    ) {}

    // ---------------------------------------------------------------------
    // Factories
    // ---------------------------------------------------------------------

    public static function ofMinor(int $amount, BrickCurrency|string $currency): self
    {
        return new self($amount, self::currencyCodeOf($currency));
    }

    /**
     * Construct from a decimal amount in the currency's major units.
     * Use a decimal string like "12.34" or an integer minor count
     * via {@see self::ofMinor()}.
     */
    public static function of(string|int $amount, BrickCurrency|string $currency): self
    {
        $code = self::currencyCodeOf($currency);
        $brick = BrickMoney::of((string) $amount, $code, new DefaultContext());

        return new self(self::brickToMinor($brick), $code);
    }

    public static function zero(BrickCurrency|string $currency): self
    {
        return new self(0, self::currencyCodeOf($currency));
    }

    // ---------------------------------------------------------------------
    // Arithmetic
    // ---------------------------------------------------------------------

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currencyCode);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor - $other->amountMinor, $this->currencyCode);
    }

    /**
     * Multiply by a decimal factor. Rounding defaults to HALF_EVEN
     * (banker's rounding); pass a different {@see \Brick\Math\RoundingMode}
     * when the domain requires it (e.g. tax on cash uses HALF_UP).
     */
    public function multipliedBy(string|int $multiplier, RoundingMode $roundingMode = self::DEFAULT_ROUNDING_MODE): self
    {
        $brick = $this->toBrick()->multipliedBy((string) $multiplier, $roundingMode);

        return new self(self::brickToMinor($brick), $this->currencyCode);
    }

    /**
     * Divide by a decimal divisor. Rounding defaults to HALF_EVEN.
     */
    public function dividedBy(string|int $divisor, RoundingMode $roundingMode = self::DEFAULT_ROUNDING_MODE): self
    {
        $normalized = (string) $divisor;
        if ($normalized === '0' || $normalized === '0.0' || $normalized === '0.00') {
            throw new InvalidArgumentException('Cannot divide Money by zero.');
        }
        $brick = $this->toBrick()->dividedBy($normalized, $roundingMode);

        return new self(self::brickToMinor($brick), $this->currencyCode);
    }

    public function negate(): self
    {
        return new self(-$this->amountMinor, $this->currencyCode);
    }

    public function abs(): self
    {
        return new self(abs($this->amountMinor), $this->currencyCode);
    }

    // ---------------------------------------------------------------------
    // Allocation (exact; no rounding loss)
    // ---------------------------------------------------------------------

    /**
     * Split into N parts. Remainder minor units (always 0..N-1) are
     * distributed one per part starting at index 0.
     *
     * @return list<self>
     */
    public function allocate(int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('Allocation parts must be at least 1.');
        }
        $ratios = array_fill(0, $parts, 1);
        $allocated = $this->toBrick()->allocate(...$ratios);

        return array_values(array_map(
            static fn (BrickMoney $m): self => new self(self::brickToMinor($m), $m->getCurrency()->getCurrencyCode()),
            $allocated
        ));
    }

    /**
     * Split into the requested target minor units. The sum of the targets
     * MUST equal the source amountMinor; this is verified and an
     * {@see \InvalidArgumentException} is thrown otherwise.
     *
     * @param  list<int>  $targets
     * @return list<self>
     */
    public function allocateToTargets(array $targets): array
    {
        if ($targets === []) {
            throw new InvalidArgumentException('Allocation targets must not be empty.');
        }
        $sum = array_sum($targets);
        if ($sum !== $this->amountMinor) {
            throw new InvalidArgumentException(sprintf(
                'Allocation targets sum (%d) does not equal source amount (%d).',
                $sum,
                $this->amountMinor
            ));
        }
        $code = $this->currencyCode;

        return array_values(array_map(
            static fn (int $t): self => new self($t, $code),
            $targets
        ));
    }

    // ---------------------------------------------------------------------
    // Comparison
    // ---------------------------------------------------------------------

    public function equals(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor === $other->amountMinor;
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->amountMinor <=> $other->amountMinor;
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function isPositive(): bool
    {
        return $this->amountMinor > 0;
    }

    public function isNegative(): bool
    {
        return $this->amountMinor < 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function isLessThanOrEqualTo(self $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    // ---------------------------------------------------------------------
    // Serialization and formatting
    // ---------------------------------------------------------------------

    /**
     * Render in the given locale (requires the `intl` PHP extension).
     */
    public function format(string $locale = 'en'): string
    {
        return $this->toBrick()->formatTo($locale);
    }

    /**
     * @return array{amount_minor: int, currency: string}
     */
    public function toArray(): array
    {
        return [
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currencyCode,
        ];
    }

    /**
     * @param  array{amount_minor: int|float|string, currency: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return self::ofMinor(
            (int) $data['amount_minor'],
            (string) $data['currency'],
        );
    }

    public function getAmountDecimal(): string
    {
        return $this->toBrick()->getAmount()->__toString();
    }

    public function getCurrency(): BrickCurrency
    {
        return BrickCurrency::of($this->currencyCode);
    }

    public function toBrick(): BrickMoney
    {
        return BrickMoney::ofMinor($this->amountMinor, $this->currencyCode);
    }

    public function __toString(): string
    {
        return $this->toBrick()->__toString();
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    private function assertSameCurrency(self $other): void
    {
        if ($this->currencyCode !== $other->currencyCode) {
            throw CurrencyMismatchException::between($this->currencyCode, $other->currencyCode);
        }
    }

    /**
     * Convert a {@see BrickMoney} to the integer minor-unit count for its currency.
     *
     * brick/money stores amounts in the currency's major units (e.g. 10.00
     * for $10.00). Multiplying by 10^fractionDigits converts the major-unit
     * amount into the integer minor-unit count we keep on this value object.
     *
     * BigInteger math is used (no floats) so values up to 2^63 minor units
     * stay exact; values beyond that exceed PHP_INT_MAX and the cast to int
     * will wrap, which is detected by callers via {@see self::isZero()} or
     * the underlying {@see \Brick\Math\BigNumber} API.
     */
    private static function brickToMinor(BrickMoney $brick): int
    {
        $fractionDigits = $brick->getCurrency()->getDefaultFractionDigits();
        $multiplier = BigInteger::of(10)->power($fractionDigits);

        return (int) $brick->getAmount()->multipliedBy($multiplier)->toInt();
    }

    private static function currencyCodeOf(BrickCurrency|string $currency): string
    {
        $code = $currency instanceof BrickCurrency ? $currency->getCurrencyCode() : $currency;
        // Cheap validation: brick/money throws UnknownCurrencyException for unknown codes.
        BrickCurrency::of($code);

        return $code;
    }
}