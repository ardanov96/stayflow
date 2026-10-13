<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Models;

use App\Models\User;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A user membership in an organization (and optionally a specific
 * property inside it). property_id NULL means the user can act on
 * every property in the organization (e.g. a group owner). P1-03
 * (RBAC) turns the `role` string into a typed enum and wires
 * the permission matrix; P1-01 only needs the row to exist so
 * downstream tenancy-aware features have a join target.
 *
 * property_id is exposed as a plain int (no belongsTo relation) to
 * keep the Platform module free of Catalog imports; the relation
 * lives in a future P1-03 Application-layer query that joins via
 * the Catalog module Contracts/ boundary.
 */
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'organization_id',
        'property_id',
        'role',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
