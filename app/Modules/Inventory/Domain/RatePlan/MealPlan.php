<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\RatePlan;

enum MealPlan: string
{
    case RoomOnly = 'room_only';
    case Breakfast = 'breakfast';
    case HalfBoard = 'half_board';
    case FullBoard = 'full_board';
    case AllInclusive = 'all_inclusive';

    public function label(): string
    {
        return match ($this) {
            self::RoomOnly => 'Room Only (EP)',
            self::Breakfast => 'Bed & Breakfast (CP)',
            self::HalfBoard => 'Half Board (MAP)',
            self::FullBoard => 'Full Board (AP)',
            self::AllInclusive => 'All Inclusive (AI)',
        };
    }
}
