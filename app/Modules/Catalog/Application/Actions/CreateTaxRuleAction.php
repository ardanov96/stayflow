<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\TaxRule;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Support\Money\TaxAppliesTo;
use App\Support\Money\TaxKind;

/**
 * Action to configure a TaxRule for a Property.
 */
final readonly class CreateTaxRuleAction
{
    public function __construct(
        private Auditor $auditor,
    ) {}

    /**
     * @param array{
     *     name: string,
     *     kind: TaxKind|string,
     *     rate_bps: int,
     *     is_inclusive?: bool,
     *     applies_to?: TaxAppliesTo|string,
     *     order?: int,
     *     is_active?: bool
     * } $data
     */
    public function execute(Property $property, array $data, ?int $actorId = null): TaxRule
    {
        $kind = $data['kind'];
        if (is_string($kind)) {
            $kind = TaxKind::from($kind);
        }

        $appliesTo = $data['applies_to'] ?? TaxAppliesTo::All;
        if (is_string($appliesTo)) {
            $appliesTo = TaxAppliesTo::from($appliesTo);
        }

        $taxRule = TaxRule::create([
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'name' => $data['name'],
            'kind' => $kind,
            'rate_bps' => $data['rate_bps'],
            'is_inclusive' => $data['is_inclusive'] ?? false,
            'applies_to' => $appliesTo,
            'order' => $data['order'] ?? 1,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->auditor->recordCustom(
            'catalog.tax_rule.created',
            new SubjectChange(
                subjectType: TaxRule::class,
                subjectId: (int) $taxRule->id,
                oldValues: null,
                newValues: [
                    'name' => $taxRule->name,
                    'kind' => $taxRule->kind->value,
                    'rate_bps' => $taxRule->rate_bps,
                    'is_inclusive' => $taxRule->is_inclusive,
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'property_id' => (int) $property->id,
                ],
            ),
            new TenantHint((int) $property->organization_id, (int) $property->id),
        );

        return $taxRule;
    }
}
