<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stub Property model. Full CRUD (rooms, policies, tax rules,
 * timezone/currency/check-in times, media, Filament resources
 * with i18n content) ships in P1-06.
 *
 * P1-01 owns the schema and the tenant-scope wiring because
 * every downstream task in the critical path (P1-07 rate plans,
 * P1-08 inventory engine, ...) needs a Property FK to hang from.
 */
class Property extends Model
{
    use BelongsToTenant;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
