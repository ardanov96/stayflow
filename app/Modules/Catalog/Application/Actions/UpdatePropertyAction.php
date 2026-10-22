<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Property\PropertyStatus;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;

/**
 * Action to update an existing Property.
 */
final readonly class UpdatePropertyAction
{
    public function __construct(
        private Auditor $auditor,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Property $property, array $data, ?int $actorId = null): Property
    {
        $oldValues = [
            'name' => $property->name,
            'status' => $property->status->value,
            'timezone' => $property->timezone,
            'currency' => $property->currency,
        ];

        if (isset($data['status']) && is_string($data['status'])) {
            $data['status'] = PropertyStatus::from($data['status']);
        }

        $property->update($data);

        $this->auditor->recordCustom(
            'catalog.property.updated',
            new SubjectChange(
                subjectType: Property::class,
                subjectId: (int) $property->id,
                oldValues: $oldValues,
                newValues: [
                    'name' => $property->name,
                    'status' => $property->status->value,
                    'timezone' => $property->timezone,
                    'currency' => $property->currency,
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'organization_id' => (int) $property->organization_id,
                ],
            ),
            new TenantHint((int) $property->organization_id, (int) $property->id),
        );

        return $property;
    }
}
