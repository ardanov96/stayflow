<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Pre-authentication login attempt log (P1-02 Identity).
 *
 * This table is deliberately NOT covered by
 * {@see BelongsToTenant}:
 * a login attempt happens before the caller is identified, so there
 * is no tenant scope to enforce. The arch test
 * (tests/Feature/Platform/Tenancy/TenantTableArchTest) lists this
 * model on the explicit exemption list with a one-line justification.
 */
class LoginAttempt extends Model
{
    protected $table = 'login_attempts';

    public $timestamps = false;

    protected $fillable = [
        'email',
        'ip_address',
        'user_agent',
        'successful',
        'failure_reason',
        'user_id',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }
}
