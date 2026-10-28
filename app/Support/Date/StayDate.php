<?php

declare(strict_types=1);

namespace App\Support\Date;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;

/**
 * A stay date: a calendar day in the property's timezone.
 *
 * A stay date is **not** an instant. It means "the night of this day in
 * this hotel", and the hotel is what decides where the day starts. A
 * guest in Bali checks in on the 28th even when it is still the 27th in
 * London.
 *
 * Stay dates are stored as `DATE` columns. Everything that writes one,
 * reads one, or does arithmetic on one goes through this value object.
 * Formatting a date by hand is the mistake this class exists to prevent
 * (the rule is in `tests/Arch/DateFormattingTest.php`).
 */
final readonly class StayDate implements Stringable
{
    private function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {}

    /**
     * Parse a stored stay date.
     *
     * The string has to be a real calendar day in the `Y-m-d` format: a
     * date that does not exist (`2026-02-30`) is rejected instead of
     * rolling over into March.
     */
    public static function fromString(string $date): self
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException(
                "A stay date must be a real date in the Y-m-d format, got \"{$date}\"."
            );
        }

        return new self(
            (int) $parsed->format('Y'),
            (int) $parsed->format('m'),
            (int) $parsed->format('d'),
        );
    }

    /**
     * The stay date as it is stored.
     */
    public function toString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function plusDays(int $days): self
    {
        return $this->shift($days);
    }

    public function minusDays(int $days): self
    {
        return $this->shift(-$days);
    }

    /**
     * The number of nights between the two stay dates.
     *
     * It counts the nights from this date **until** the other one: it is
     * positive when the other date is later, zero when they are the same
     * day, and negative when the other date is in the past.
     */
    public function nightsUntil(self $other): int
    {
        $seconds = $other->midnightUtc()->getTimestamp() - $this->midnightUtc()->getTimestamp();

        return (int) round($seconds / 86400);
    }

    /**
     * The stay dates of a stay: the first day up to (but excluding) the
     * last one.
     *
     * A stay from the 10th to the 12th covers the nights of the 10th and
     * the 11th.
     *
     * @return list<self>
     */
    public function range(self $end): array
    {
        $dates = [];
        $current = $this;

        while ($current->compare($end) < 0) {
            $dates[] = $current;
            $current = $current->plusDays(1);
        }

        return $dates;
    }

    public function compare(self $other): int
    {
        return $this->midnightUtc() <=> $other->midnightUtc();
    }

    public function isBefore(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function isBetween(self $from, self $to): bool
    {
        return $this->compare($from) >= 0 && $this->compare($to) <= 0;
    }

    /**
     * The first instant of this day in the given timezone.
     */
    public function toDateTimeImmutable(string $timezone): DateTimeImmutable
    {
        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d 00:00:00', $this->year, $this->month, $this->day),
            new DateTimeZone($timezone),
        );
    }

    /**
     * The first instant of this day in UTC.
     *
     * The stay date itself does not change: it is a day, not an instant.
     * This is only for the code that has to compare a stay date with a
     * stored timestamp.
     */
    public function toUtcDateTime(): DateTimeImmutable
    {
        return $this->midnightUtc();
    }

    private function midnightUtc(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d 00:00:00', $this->year, $this->month, $this->day),
            new DateTimeZone('UTC'),
        );
    }

    private function shift(int $days): self
    {
        $shifted = $this->midnightUtc()->modify(sprintf('%+d days', $days));

        return new self(
            (int) $shifted->format('Y'),
            (int) $shifted->format('m'),
            (int) $shifted->format('d'),
        );
    }
}
