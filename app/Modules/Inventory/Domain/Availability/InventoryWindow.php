<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Availability;

use DateTimeImmutable;

final readonly class InventoryWindow
{
    /**
     * Default rolling window duration in days (24 months ~ 730 days).
     * Per docs/ARCHITECTURE.md §5.1.
     */
    public const DEFAULT_WINDOW_DAYS = 730;

    /**
     * Check if a given date string is within the maximum booking window from today.
     */
    public static function isWithinWindow(string $date, int $maxDays = self::DEFAULT_WINDOW_DAYS): bool
    {
        $today = new DateTimeImmutable('today');
        $target = new DateTimeImmutable($date);

        if ($target < $today) {
            return false;
        }

        $maxDate = $today->modify("+{$maxDays} days");

        return $target <= $maxDate;
    }

    /**
     * Get the default end date for materialization (today + 730 days).
     */
    public static function defaultMaterializationEndDate(): string
    {
        return (new DateTimeImmutable('today'))->modify('+'.self::DEFAULT_WINDOW_DAYS.' days')->format('Y-m-d');
    }
}
