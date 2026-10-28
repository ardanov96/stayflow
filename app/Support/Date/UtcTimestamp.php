<?php

declare(strict_types=1);

namespace App\Support\Date;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Date;

/**
 * A timestamp, as it is stored.
 *
 * Every timestamp of Stayflow is stored in UTC, to the second. The
 * timezone of a property only decides where a *day* starts; the moment
 * itself is always UTC, so two hotels in two timezones agree on when
 * something happened.
 */
final class UtcTimestamp
{
    /**
     * The current moment, in UTC, as it is written to the database.
     */
    public static function now(?DateTimeInterface $now = null): string
    {
        return self::of($now ?? Date::now('UTC'));
    }

    /**
     * A moment, in UTC, as it is written to the database.
     */
    public static function of(DateTimeInterface $moment): string
    {
        return Date::instance($moment)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }

    /**
     * A moment, in UTC, as a compact sortable identifier.
     *
     * It is used where a timestamp has to be a name — a release
     * directory, for example — and the name has to sort like the time it
     * was created.
     */
    public static function compact(?DateTimeInterface $now = null): string
    {
        return Date::instance($now ?? Date::now('UTC'))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Ymd-His');
    }
}
