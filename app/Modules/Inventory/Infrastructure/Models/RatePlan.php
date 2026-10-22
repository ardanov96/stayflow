<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\PropertyPolicy;
use App\Modules\Inventory\Domain\RatePlan\DerivationType;
use App\Modules\Inventory\Domain\RatePlan\MealPlan;
use App\Modules\Inventory\Domain\RatePlan\RateDerivation;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\RatePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RatePlan model.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property string $code
 * @property string $name
 * @property array<string, string>|null $description
 * @property MealPlan $meal_plan
 * @property int|null $cancellation_policy_id
 * @property int|null $parent_rate_plan_id
 * @property DerivationType|null $derivation_type
 * @property int|null $derivation_value
 * @property bool $is_active
 * @property int $sort_order
 */
class RatePlan extends Model
{
    /** @use HasFactory<RatePlanFactory> */
    use HasFactory;

    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'property_id',
        'code',
        'name',
        'description',
        'meal_plan',
        'cancellation_policy_id',
        'parent_rate_plan_id',
        'derivation_type',
        'derivation_value',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => 'array',
            'meal_plan' => MealPlan::class,
            'derivation_type' => DerivationType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function cancellationPolicy(): BelongsTo
    {
        return $this->belongsTo(PropertyPolicy::class, 'cancellation_policy_id');
    }

    public function parentRatePlan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_rate_plan_id');
    }

    public function derivedRatePlans(): HasMany
    {
        return $this->hasMany(self::class, 'parent_rate_plan_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RatePrice::class);
    }

    public function isDerived(): bool
    {
        return $this->parent_rate_plan_id !== null
            && $this->derivation_type !== null
            && $this->derivation_value !== null;
    }

    public function getDerivation(): ?RateDerivation
    {
        if (! $this->isDerived()) {
            return null;
        }

        /** @var DerivationType $type */
        $type = $this->derivation_type;
        /** @var int $value */
        $value = $this->derivation_value;

        return new RateDerivation($type, $value);
    }
}
