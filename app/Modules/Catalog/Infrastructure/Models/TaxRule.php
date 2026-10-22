<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use App\Support\Money\TaxAppliesTo;
use App\Support\Money\TaxKind;
use App\Support\Money\TaxRule as DomainTaxRule;
use Database\Factories\TaxRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted tax or service-charge rule for a property.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property string $name
 * @property TaxKind|string $kind
 * @property int $rate_bps
 * @property bool $is_inclusive
 * @property TaxAppliesTo|string $applies_to
 * @property int $order
 * @property bool $is_active
 */
class TaxRule extends Model
{
    /** @use HasFactory<TaxRuleFactory> */
    use HasFactory;

    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'property_id',
        'name',
        'kind',
        'rate_bps',
        'is_inclusive',
        'applies_to',
        'order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TaxKind::class,
            'rate_bps' => 'integer',
            'is_inclusive' => 'boolean',
            'applies_to' => TaxAppliesTo::class,
            'order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function toDomainRule(): DomainTaxRule
    {
        $kind = $this->kind instanceof TaxKind ? $this->kind : TaxKind::from((string) $this->kind);
        $appliesTo = $this->applies_to instanceof TaxAppliesTo ? $this->applies_to : TaxAppliesTo::from((string) $this->applies_to);

        return new DomainTaxRule(
            name: $this->name,
            kind: $kind,
            rateBps: $this->rate_bps,
            inclusive: $this->is_inclusive,
            appliesTo: $appliesTo,
            order: $this->order,
        );
    }
}
