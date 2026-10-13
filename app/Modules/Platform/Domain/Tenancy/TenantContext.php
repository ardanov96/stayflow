<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Tenancy;

use InvalidArgumentException;

/**
 * Immutable value object holding the tenant context for a unit of work
 * (an HTTP request, a queued job, a console command, a test).
 *
 * Every tenant-scoped query in Stayflow is filtered by organizationId.
 * propertyId is optional: when set, queries are additionally restricted
 * to that property (used by property-level staff such as front-desk or
 * housekeeping once RBAC is wired in by P1-03). When null, the query
 * runs across all properties in the organization.
 *
 * See docs/TENANCY.md for the full tenant model.
 */
final readonly class TenantContext
{
    private function __construct(
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

    public static function forOrganization(int $organizationId): self
    {
        return new self($organizationId);
    }

    public static function forOrganizationAndProperty(int $organizationId, int $propertyId): self
    {
        return new self($organizationId, $propertyId);
    }

    public function withProperty(?int $propertyId): self
    {
        return new self($this->organizationId, $propertyId);
    }

    public function equals(self $other): bool
    {
        return $this->organizationId === $other->organizationId
            && $this->propertyId === $other->propertyId;
    }

    public function __toString(): string
    {
        return $this->propertyId === null
            ? "org:{$this->organizationId}"
            : "org:{$this->organizationId}/prop:{$this->propertyId}";
    }
}
