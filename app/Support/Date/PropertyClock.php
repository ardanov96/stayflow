<?php

declare(strict_types=1);

namespace App\Support\Date;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Date;

/**
 * The clock of a property.
 *
 * Stayflow stores every timestamp in UTC, but a hotel does not live in
 * UTC: its day starts when its own clock says so. This is the only place
 * that turns an instant into "the day it is at the hotel", so the
 * midnight boundary is decided once and not in every action.
 *
 * The instant can be injected, which is what makes the boundary
 * testable: a property in Bali and a property in Los Angeles do not
 * share a "today".
 */
final class PropertyClock
{
    /**
     * The current moment, in the timezone of the property.
     */
    public function now(string $timezone, ?DateTimeInterface $now = null): DateTimeImmutable
    {
        $moment = $now === null
            ? Date::now('UTC')
            : Date::instance($now);

        return $moment->toImmutable()->setTimezone(new DateTimeZone($timezone));
    }

    /**
     * The current stay date of the property.
     */
    public function today(string $timezone, ?DateTimeInterface $now = null): StayDate
    {
        return StayDate::fromString($this->now($timezone, $now)->format('Y-m-d'));
    }

    /**
     * The business date of the property: the operating day the hotel is
     * in right now.
     *
     * It is the same as the stay date until the night audit closes it
     * (task `P2-13`); the two are separate concepts, and the night audit
     * is what makes them diverge.
     */
    public function businessDate(string $timezone, ?DateTimeInterface $now = null): StayDate
    {
        return $this->today($timezone, $now);
    }
}
