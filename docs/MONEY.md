# Money and tax policy

Source of truth for how Stayflow stores, computes, and presents money.
This document is referenced by `docs/ENGINEERING_STANDARDS.md` section
2.1 and by every module that touches currency.

## 1. Storage

Every money amount is stored as an **integer count of the currency's
minor units** together with the ISO 4217 currency code. Floats are
forbidden at the type level (`AGENTS.md` section 3.2).

Examples of minor units per currency:

- USD: cent (1/100 USD). `Money::ofMinor(100, 'USD')` = USD 1.00.
- EUR: cent (1/100 EUR). `Money::ofMinor(100, 'EUR')` = EUR 1.00.
- IDR: rupiah (no sub-unit in modern circulation, but ISO 4217 still
  lists two fraction digits). `Money::ofMinor(100, 'IDR')` = IDR 100.00.
- JPY: yen (no sub-unit). `Money::ofMinor(100, 'JPY')` = JPY 100.

**IDR gotcha.** ISO 4217 lists IDR with two fraction digits even though
Indonesia demonetised the sen in 1971 and no rupiah price is ever
quoted in sen. `brick/money` follows ISO 4217, so `Money::of('100',
'IDR')` yields `100.00 IDR` = 10_000 minor units. For modern
whole-rupiah amounts use `Money::ofMinor(100, 'IDR')` (1 IDR = 1
minor unit). Stayflow persists rates as integer minor units via
`Money::ofMinor()`, so this surprise is contained to the factory call
sites.

## 2. The `Money` value object

```php
use App\Support\Money\Money;

$price    = Money::ofMinor(1_500_000, 'IDR'); // 1.5M IDR
$discount = $price->multipliedBy('0.10');     // 150_000 IDR
$total    = $price->minus($discount);         // 1_350_000 IDR

// Split across three nights
$perNight = $total->allocate(3);              // [450_000, 450_000, 450_000]
```

Construction factories:

- `Money::ofMinor(int, currency)` - persisted / network values; the
  integer IS the minor count.
- `Money::of(string, currency)` - user input; decimal string in major
  units (`'12.34'`, `'10'`).
- `Money::zero(currency)` - initial / cleared values.

Arithmetic:

- `plus` / `minus` - exact integer math; same currency required
  (`CurrencyMismatchException` otherwise).
- `multipliedBy(string, RoundingMode = HALF_EVEN)` - `multipliedBy('0.11')`
  etc. Rounding mode is overridable.
- `dividedBy(string, RoundingMode = HALF_EVEN)` - `dividedBy('3')` etc.
  Rejects zero divisor.
- `negate` / `abs` - sign helpers.
- `allocate(int $parts)` - split into N exact parts (sum equals source).
- `allocateToTargets(int[])` - wrap the given minor-unit targets;
  asserts the sum equals the source.

Comparison: `equals`, `compareTo` (returns -1/0/1), `isZero`,
`isPositive`, `isNegative`, `isGreaterThan`, `isLessThan`,
`isGreaterThanOrEqualTo`, `isLessThanOrEqualTo`. All require the same
currency.

Serialization: `toArray()` returns `['amount_minor' => int, 'currency' =>
string]`. `fromArray()` is the inverse. Use these shapes on JSON
endpoints and queue payloads.

The underlying `brick/money` instance is reachable via `toBrick()` when
interoperating with libraries that expect the brick type (e.g. a
third-party PDF invoice renderer).

## 3. Rounding

**Default: `RoundingMode::HALF_EVEN` (banker's rounding).** This is the
only mode Stayflow uses unless the domain explicitly demands another.
Banker's rounding removes the long-term upward bias of HALF_UP and is
the IEEE 754 default for binary float as well as the recommendation
in ISO 20022 and most payment-network specifications.

When to override:

- Default: `HALF_EVEN`. Unbiased long-term rounding; matches ISO 20022.
- Tax on cash payments (some local regulations): `HALF_UP`. Required
  by Indonesian tax law for certain PB1 line items. Set on the
  `TaxRule`, not the `Money`.
- Final invoice round (rare): `HALF_UP`. Bias toward the hotel for
  the last cent. Discuss with the pilot first.

The `Money` value object accepts a `RoundingMode` on every
`multipliedBy` / `dividedBy` call. The `TaxRule` accepts a
`roundingMode` that the calculator forwards. Persistence and storage
never round; the integer minor count is the truth.

## 4. Tax and service charge

`docs/DOMAIN_MODEL.md` section 2 Catalog defines a `TaxRule` per
property. The runtime types in `App\Support\Money` mirror those fields
plus a few conveniences:

```php
use App\Support\Money\TaxAppliesTo;
use App\Support\Money\TaxCalculator;
use App\Support\Money\TaxKind;
use App\Support\Money\TaxRule;

$vat = TaxRule::exclusive('VAT', 1100, TaxKind::Tax);           // 11 % exclusive
$sc  = TaxRule::inclusive('SC',  1000, TaxKind::ServiceCharge); // 10 % inclusive

$breakdown = (new TaxCalculator())->apply($subtotal, $vat, $sc);
```

Rules:

- **Basis points.** Rates are stored as integer basis points (bps):
  `1000` = 10 %, `1100` = 11 %, `10000` = 100 %. No decimal rates in
  persistence.
- **Inclusive vs exclusive.**
  - *Exclusive:* the rate is added on top of the running total. The
    default for most taxes (VAT, PB1, sales tax).
  - *Inclusive:* the rate is already inside the subtotal. The
    calculator extracts the tax for reporting; the total does not
    change. Use for "rate includes service charge" quotes.
- **Order.** Lower `order` runs first. Exclusive rules accumulate on
  the running total; inclusive rules always extract from the original
  subtotal (not the running total) to avoid circular logic.
- **Applies-to.** `TaxAppliesTo` is `room` / `addon` / `all`. The
  calculator applies every rule it receives; the booking engine
  filters by `appliesTo` before calling it.

Reading a `TaxBreakdown`:

- `subtotal` - the original input.
- `total` - subtotal + sum of exclusive lines (rounded).
- `lines` - one `TaxLine` per applied rule (with `inclusive` flag).
- `taxTotal()` / `serviceChargeTotal()` - sum lines by `kind`.
- `grandChargesTotal()` - sum of every line, both kinds.

## 5. Worked examples

### 5.1 11 % VAT on a USD 100.00 quote (exclusive)

```
subtotal       = USD 100.00
vat            = 100.00 * 0.11 = USD 11.00
total          = USD 111.00
```

### 5.2 10 % service charge included in a USD 110.00 rate (inclusive)

```
subtotal       = USD 110.00
net            = 110.00 / 1.10 = USD 100.00
service_charge = 110.00 - 100.00 = USD 10.00
total          = USD 110.00 (unchanged; the SC was already in the rate)
```

### 5.3 Chained SC 10 % then VAT 11 % on USD 100.00 (both exclusive)

```
subtotal       = USD 100.00
service_charge = 100.00 * 0.10 = USD 10.00
running_total  = USD 110.00
vat            = 110.00 * 0.11 = USD 12.10
total          = USD 122.10
```

## 6. Anti-patterns

- **Do not** compute money with `float`. PHP loses precision well
  below the cent; rates like `0.1` round to `0.10000000000000000555`.
  Use `Money::multipliedBy('0.10')`.
- **Do not** sum two `Money` values in different currencies. Convert
  one side through the project's currency conversion path first. The
  conversion path is not in MVP scope; see ADR for follow-up.
- **Do not** log a `Money` value with `dd()` or `var_dump()` in
  production: it may include the underlying brick `BigNumber` whose
  string form is verbose. Use `(string) $money` or `$money->toArray()`.
- **Do not** store `Money` as a JSON column with the `toString()` form
  ("USD 12.34"); the parser cannot reliably invert it. Store
  `toArray()` shape or add a custom Eloquent cast (P1-01 / P1-06).