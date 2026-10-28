# DATES — time, stay dates and timezones

Documentation for task `P1-12` (locale, timezone and date conventions).

## 1. The three kinds of time

Stayflow deals with three different things that all look like "a date".
Confusing them is how a hotel ends up a day ahead of its guests.

| | What it is | Where it lives | The helper |
|---|---|---|---|
| **The instant** | A moment in time. When something happened. | A `TIMESTAMP`, always in **UTC** | `App\Support\Date\UtcTimestamp` |
| **The stay date** | A calendar day of a hotel. The night of a room. | A `DATE`, in the **timezone of the property** | `App\Support\Date\StayDate` |
| **The business date** | The operating day of a hotel, closed by the night audit. | A `DATE`, in the timezone of the property | `PropertyClock::businessDate()` |

The **instant** is the same everywhere: two hotels in two timezones agree
on when a booking happened. The **stay date** is not: the 28th of October
in Bali is the 27th of October in London, and the hotel decides which one
its guest arrives on.

## 2. The rule

> The day of a hotel starts when the clock of the hotel says so.

Everywhere a *day* is decided, the timezone of the **property** is used.
The timezone of the server is never used to decide whether a night is in
the past.

The bug this prevents: at 17:00 UTC it is already the 28th in Bali
(UTC+8) and still the 27th in Los Angeles (UTC-7 in October). A server
that says "today" without asking the property books a night that has
already started in the hotel — and rejects a night that has not.

## 3. The helpers

Everything lives in `App\Support\Date`. The helpers do not depend on the
framework (the arch test checks it), and the instant they work with can
be injected, so the boundary is testable.

### `StayDate`

A calendar day of a property.

```php
$date = StayDate::fromString('2026-10-28');   // rejects 2026-02-30
$date->toString();                            // '2026-10-28' (the stored form)
$date->plusDays(1)->toString();               // '2026-10-29'
$date->minusDays(1)->toString();              // '2026-10-27'
$date->nightsUntil($other);                   // the nights from this date until the other
$date->range($checkOut);                      // the stay dates: [checkIn, checkOut)
$date->isBefore($other); $date->isAfter($other); $date->equals($other);
$date->toDateTimeImmutable('Asia/Makassar');  // the first instant of that day
```

A stay date is a `DATE`, so it never has a time and never has a
timezone. `toDateTimeImmutable()` is the only way to turn it into an
instant, and it asks for the timezone.

### `PropertyClock`

The clock of a property.

```php
$clock = new PropertyClock();
$clock->now('Asia/Makassar');            // the moment, in the time of the hotel
$clock->today('Asia/Makassar');          // the stay date of the hotel, right now
$clock->businessDate('Asia/Makassar');   // the operating day (the night audit closes it)
```

The instant can be given, and the clock follows the Laravel clock, so
the tests can freeze it.

### `DateFormatter`

The formatting for the person who reads it: the locale is the one the
reader chose (the `locale` cookie), the timezone is the one of the
property.

```php
$formatter = new DateFormatter('id');
$formatter->date($stayDate);                       // '28 Okt 2026'
$formatter->dateTime($moment, 'Asia/Makassar');    // the date and the time of the hotel
$formatter->time($moment, 'Asia/Makassar');
$formatter->number(1234567);                      // '1.234.567'
```

The formatting is done by ICU, so the `id`, `en` and `zh` of OQ-06 come
out the way the locale says, day names and month names included.

### `UtcTimestamp`

The instant, as it is stored.

```php
UtcTimestamp::now();      // '2026-10-27 17:00:00' in UTC
UtcTimestamp::of($moment);
UtcTimestamp::compact();  // '20261027170000', for a name that has to sort
```

## 4. The lint rule

`tests/Arch/DateFormattingTest.php` fails the build when a date is
formatted by hand outside `App\Support\Date`: no `->format(`, no
`date('...')`, no `strftime(`.

It is a test and not a convention on purpose: a convention is forgotten,
a test is not.

## 5. What the rule caught

The inventory engine computed "today" with the timezone of the server:

```php
$today = new DateTimeImmutable('today');   // the day of the server
```

The booking window and the materialization of the inventory days
followed it. The window is now counted in the days of the property
(`InventoryWindow::isWithinWindow()` takes the timezone), and the
materialization starts on the day the hotel is in.

The proofs are in `tests/Feature/Inventory/TimezoneBoundaryTest.php`: at
17:00 UTC, the same night is refused for the hotel in Bali and accepted
for the hotel in Los Angeles.

## 6. What is not settled yet

- The **business date** is the same as the stay date until the night
  audit closes it (task `P2-13`). The two are separate concepts and the
  night audit is what makes them diverge.
- The **stay date of a booking** is the check-in date of the property,
  not of the guest: a guest who arrives at 02:00 on the 28th stays the
  night of the 28th.
- The **fiscal year** of the invoices (task `P2-10`) follows the tax
  rules of the property, not the calendar year of the server.

## 7. Related documents

- `docs/ENGINEERING_STANDARDS.md` §2.2 — the rule in one line.
- `docs/INVENTORY.md` §3 — the rolling window of the materialized days.
- `docs/DOMAIN_MODEL.md` §1 — the business date.
- `docs/ARCHITECTURE.md` §5.1 — the booking window.