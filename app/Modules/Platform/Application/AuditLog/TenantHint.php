<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\AuditLog;

use InvalidArgumentException;

/**
 * Immutable value object describing the tenant scope an audit row
 * must carry. The {@see \App\Modules\Platform\Application\AuditLog\Ports\Auditor}
 * port accepts this as a separate parameter (next to the
 * {@see SubjectChange}) so the calling layer is the only place that
 * has to know about tenant resolution.
 *
 * Resolution order used by the `Auditable` trait:
 *  1. If the subject model already has `organization_id` set
 *     (either by the `BelongsToTenant` `creating` observer, or
 *     explicitly by a seeder / factory), use it. `property_id` is
 *     read from the model when it is present.
 *  2. If the subject has no `organization_id` column or it is
 *     empty, fall back to the active `OrganizationContext` (the
 *     same source `BelongsToTenant` itself uses for auto-fill).
 *  3. If both are empty, refuse to write the audit row — the same
 *     "no tenant, no write" rule the platform enforces everywhere
 *     else.
 *
 * Decoupling the hint from the {@see \App\Modules\Platform\Domain\Tenancy\TenantContext}
 * domain VO keeps the Application layer free of an Eloquent-edge
 * dependency; the trait is the one place that bridges the two.
 */
final readonly class TenantHint
{
    public function __construct(
        public int $organizationId,
        public ?int $propertyId = null,
    ) {
        if ($organizationId < 1) {
            throw new InvalidArgumentException('organizationId must be a positive integer.');
        }
        if ($propertyId !== null && $propertyId < 1) {
            throw new InvalidArgumentException('propertyId must be a positive integer when set.');
        }
    }
}