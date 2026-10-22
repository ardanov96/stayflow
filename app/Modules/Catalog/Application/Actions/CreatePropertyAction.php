<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Property\PropertyStatus;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use Illuminate\Support\Str;

/**
 * Action to create a new Property within the active organization.
 */
final readonly class CreatePropertyAction
{
    public function __construct(
        private Auditor $auditor,
    ) {}

    /**
     * @param array{
     *     name: string,
     *     slug?: string,
     *     status?: PropertyStatus|string,
     *     timezone?: string,
     *     currency?: string,
     *     check_in_time?: string,
     *     check_out_time?: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     address?: array<string, mixed>|null,
     *     description?: array<string, string>|null
     * } $data
     */
    public function execute(int $organizationId, array $data, ?int $actorId = null): Property
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);
        $status = $data['status'] ?? PropertyStatus::Active;
        if (is_string($status)) {
            $status = PropertyStatus::from($status);
        }

        $property = Property::create([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'slug' => $slug,
            'status' => $status,
            'timezone' => $data['timezone'] ?? 'Asia/Makassar',
            'currency' => $data['currency'] ?? 'IDR',
            'check_in_time' => $data['check_in_time'] ?? '14:00',
            'check_out_time' => $data['check_out_time'] ?? '11:00',
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        $this->auditor->recordCustom(
            'catalog.property.created',
            new SubjectChange(
                subjectType: Property::class,
                subjectId: (int) $property->id,
                oldValues: null,
                newValues: [
                    'name' => $property->name,
                    'slug' => $property->slug,
                    'status' => $property->status->value,
                ],
                metadata: [
                    'actor_id' => $actorId,
                    'organization_id' => $organizationId,
                ],
            ),
            new TenantHint($organizationId, (int) $property->id),
        );

        return $property;
    }
}
