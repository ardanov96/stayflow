<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use App\Support\Money\Money;
use Database\Factories\RatePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RatePrice model.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property int $rate_plan_id
 * @property int $room_type_id
 * @property string $date
 * @property int $base_price_minor
 * @property string $currency
 * @property int $extra_adult_price_minor
 * @property int $extra_child_price_minor
 */
class RatePrice extends Model
{
    /** @use HasFactory<RatePriceFactory> */
    use HasFactory;

    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'property_id',
        'rate_plan_id',
        'room_type_id',
        'date',
        'base_price_minor',
        'currency',
        'extra_adult_price_minor',
        'extra_child_price_minor',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'base_price_minor' => 'integer',
            'extra_adult_price_minor' => 'integer',
            'extra_child_price_minor' => 'integer',
        ];
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function basePriceMoney(): Money
    {
        return Money::ofMinor($this->base_price_minor, $this->currency);
    }

    public function extraAdultPriceMoney(): Money
    {
        return Money::ofMinor($this->extra_adult_price_minor, $this->currency);
    }

    public function extraChildPriceMoney(): Money
    {
        return Money::ofMinor($this->extra_child_price_minor, $this->currency);
    }
}
