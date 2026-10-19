<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Platform\Application\Identity\Ports\UserIdentityGateway;
use App\Modules\Platform\Infrastructure\Models\Membership;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The auth root for Stayflow. P1-01 (Tenancy) added the Memberships
 * relation; P1-02 (Identity) adds the 2FA / lockout columns and the
 * `hasTwoFactor()` / `requiresTwoFactor()` helpers used by the
 * Application layer through {@see UserIdentityGateway}.
 *
 * 2FA secret is encrypted at rest (Laravel `encrypted` cast) so a
 * database dump does not yield usable material. Recovery codes are
 * stored as a JSON array of Argon2id hashes; plaintext codes are
 * shown to the user once and never persisted.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_login_ip' => 'string',
            'failed_login_count' => 'integer',
            'locked_until' => 'datetime',
        ];
    }

    /**
     * Memberships join a user to organizations (and optionally to a
     * specific property). The relation is the source of truth for
     * tenant context (P1-02 replaces the dev-only header-based
     * SetTenantContext with an auth-aware resolver that walks
     * this relation).
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * True when the user has a confirmed TOTP secret. The
     * unconfirmed-but-stored state (mid-enrollment) is treated as
     * "not enrolled" so a user who abandons the QR-code scan
     * doesn't accidentally satisfy the 2FA check on their next
     * login.
     */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }

    /**
     * True when the user is currently locked out of password login.
     * The `locked_until` timestamp is a hard ceiling; the soft
     * `RateLimiter` cap (driven by the IP-based secondary throttle)
     * is a separate concern and does not flip this flag.
     */
    public function isLocked(): bool
    {
        // `locked_until` is cast to datetime, so at runtime this
        // is a Carbon instance. PHPStan/larastan reports the cast
        // as a string|null because it has not been taught the
        // project's migrations; comparing the raw cast value
        // (always an ISO-like string) against `now()` is correct
        // at runtime and is what the BelongsToTenantScope does
        // for timestamps in P1-01.
        $raw = $this->getAttribute('locked_until');
        if ($raw === null || $raw === '') {
            return false;
        }

        return (string) $raw > now()->toDateTimeString();
    }

    /**
     * True when any of the user's Memberships has a role that
     * mandates 2FA. Today the privileged set is `owner` and
     * `manager`; P1-03 (RBAC) will own the canonical list and this
     * method will read from the role enum's `requiresTwoFactor()`
     * rather than hard-coding the strings.
     */
    public function requiresTwoFactor(): bool
    {
        $privileged = ['owner', 'manager'];
        $this->loadMissing('memberships');

        return $this->memberships
            ->whereIn('role', $privileged)
            ->isNotEmpty();
    }
}
