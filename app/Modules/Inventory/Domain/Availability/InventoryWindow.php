<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Availability;

use App\Support\Date\PropertyClock;
use App\Support\Date\StayDate;
use DateTimeInterface;

/**
 * The rolling window of the dates that can be booked.
 *
 * The window is counted in the **days of the property**, not in the days
 * of the server: a hotel in Bali is already on the 28th when it is still
 * the 27th in UTC. Getting this wrong moves the window by a day and
 * lets a guest book a night that has already started.
 */
final readonly class InventoryWindow
{
    /**
     * Default rolling window duration in days (24 months ~ 730 days).
     * Per docs/ARCHITECTURE.md §5.1.
     */
    public const DEFAULT_WINDOW_DAYS = 730;

    /**
     * Whether a stay date is inside the booking window of the property.
     */
    public static function isWithinWindow(
        string $date,
        string $timezone,
        int $maxDays = self::DEFAULT_WINDOW_DAYS,
        ?DateTimeInterface $now = null,
    ): bool {
        $today = (new PropertyClock)->today($timezone, $now);
        $target = StayDate::fromString($date);

        if ($target->isBefore($today)) {
            return false;
        }

        return $target->compare($today->plusDays($maxDays)) <= 0;
    }

    /**
     * The default end date of the materialization: the last day of the
     * window, in the days of the property.
     */
    public static function defaultMaterializationEndDate(
        string $timezone,
        ?DateTimeInterface $now = null,
    ): string {
        return (new PropertyClock)
            ->today($timezone, $now)
            ->plusDays(self::DEFAULT_WINDOW_DAYS)
            ->toString();
    }
}
