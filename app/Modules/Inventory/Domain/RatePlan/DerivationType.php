<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\RatePlan;

enum DerivationType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
