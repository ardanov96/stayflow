<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\RestrictionDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RestrictionDay model.
 *
 * Per-date rate plan and room type booking restrictions.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property int $rate_plan_id
 * @property int $room_type_id
 * @property string $date
 * @property int|null $min_stay
 * @property int|null $max_stay
 * @property bool $cta
 * @property bool $ctd
 * @property bool $stop_sell
 */
class RestrictionDay extends Model
{
    /** @use HasFactory<RestrictionDayFactory> */
    use HasFactory;

    use BelongsToTenant;

    protected $table = 'restrictions_days';

    protected $fillable = [
        'organization_id',
        'property_id',
        'rate_plan_id',
        'room_type_id',
        'date',
        'min_stay',
        'max_stay',
        'cta',
        'ctd',
        'stop_sell',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'min_stay' => 'integer',
            'max_stay' => 'integer',
            'cta' => 'boolean',
            'ctd' => 'boolean',
            'stop_sell' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RatePlan, $this>
     */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
