<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Identity;

enum AuthOutcomeStatus: string
{
    case AUTHENTICATED = 'authenticated';
    case TWO_FACTOR_REQUIRED = 'two_factor_required';
    case FAILED = 'failed';
}