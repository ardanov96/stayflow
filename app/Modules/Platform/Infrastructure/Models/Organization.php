<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The top-level tenant in Stayflow. One row = one customer account
 * (a hotel group or an independent owner). ADR-0003: the schema
 * is shared; rows are isolated by the BelongsToTenant global scope
 * on every other table.
 *
 * This model deliberately does NOT use {@see \App\Modules\Platform\
 * Infrastructure\Concerns\BelongsToTenant}: Organization is the
 * tenant, it does not belong to one. Reads of Organization rows
 * happen from the auth boundary (login, registration, plan
 * management) and from system jobs (nightly billing) that bypass
 * the scope.
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'default_locale',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
