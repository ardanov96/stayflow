<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Identity;

/**
 * Closed set of reasons a login attempt can fail. Stored on
 * `login_attempts.failure_reason` for ops dashboards and rate-limit
 * forensics. Adding a new value requires a migration if the column
 * ever becomes a SQL CHECK constraint; today it is a free string and
 * the enum is the canonical source of truth.
 */
enum LoginFailureReason: string
{
    case INVALID_CREDENTIALS = 'invalid_credentials';
    case ACCOUNT_LOCKED = 'account_locked';
    case TWO_FACTOR_REQUIRED = 'two_factor_required';
    case TWO_FACTOR_FAILED = 'two_factor_failed';
    case RATE_LIMITED = 'rate_limited';
}
