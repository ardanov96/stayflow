<?php

declare(strict_types=1);

namespace App\Support\Date;

use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use NumberFormatter;

/**
 * Formats dates and numbers for the person who reads them.
 *
 * The locale is the one the reader chose (the `locale` cookie of the
 * web shell), the timezone is the one of the property: a guest sees the
 * time of the hotel, not the time of the server.
 *
 * The formatting is done by the ICU library, so the `id`, `en` and `zh`
 * of OQ-06 come out the way the locale says, including the day and the
 * month names.
 */
final class DateFormatter
{
    public function __construct(private readonly string $locale = 'en') {}

    /**
     * A stay date, without a time.
     */
    public function date(StayDate $date, int $style = IntlDateFormatter::MEDIUM): string
    {
        return $this->formatter($style, IntlDateFormatter::NONE, 'UTC')->format(
            $date->toUtcDateTime(),
        );
    }

    /**
     * A moment, in the timezone of the property.
     */
    public function dateTime(
        DateTimeInterface $moment,
        string $timezone,
        int $dateStyle = IntlDateFormatter::MEDIUM,
        int $timeStyle = IntlDateFormatter::SHORT,
    ): string {
        return $this->formatter($dateStyle, $timeStyle, $timezone)->format($moment);
    }

    /**
     * The time of the moment, in the timezone of the property.
     */
    public function time(DateTimeInterface $moment, string $timezone): string
    {
        return $this->formatter(IntlDateFormatter::NONE, IntlDateFormatter::SHORT, $timezone)->format($moment);
    }

    /**
     * A number, with the separators and the digits of the locale.
     */
    public function number(float|int $number, int $decimals = 0): string
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);

        return $formatter->format($number);
    }

    private function formatter(int $dateStyle, int $timeStyle, string $timezone): IntlDateFormatter
    {
        return new IntlDateFormatter(
            $this->locale,
            $dateStyle,
            $timeStyle,
            new DateTimeZone($timezone),
            IntlDateFormatter::GREGORIAN,
        );
    }
}
