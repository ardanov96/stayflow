<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\AuditLog;

use InvalidArgumentException;

/**
 * Immutable value object describing the change to a single subject
 * row that the {@see \App\Modules\Platform\Application\AuditLog\Ports\Auditor}
 * port persists into the `audit_logs` table.
 *
 * Pure PHP, no Eloquent. The Infrastructure layer is responsible for
 * building one of these from a `Model` inside the {@see \App\Modules\Platform\Infrastructure\Concerns\Auditable}
 * trait, so Application code never sees an Eloquent type.
 *
 * `oldValues` and `newValues` are Eloquent "attribute" arrays (the
 * raw row data BEFORE Eloquent casts), not the model's public
 * relations. Sensitive attributes (passwords, 2FA secrets, recovery
 * codes, PII) are filtered out by the trait before this DTO is built
 * so they never reach the storage layer.
 */
final readonly class SubjectChange
{
    public function __construct(
        public int $subjectId,
        public string $subjectType,
        public ?array $oldValues = null,
        public ?array $newValues = null,
        public ?array $metadata = null,
    ) {
        if ($subjectId < 1) {
            throw new InvalidArgumentException('subjectId must be a positive integer.');
        }
        if ($subjectType === '') {
            throw new InvalidArgumentException('subjectType must be a non-empty string.');
        }
        if (strlen($subjectType) > 255) {
            throw new InvalidArgumentException('subjectType must be at most 255 characters.');
        }
    }

    /**
     * Convenience factory for the four CRUD-shaped events the
     * `Auditable` trait emits. The `event` argument is a raw string so
     * the trait does not have to depend on the {@see \App\Modules\Platform\Domain\AuditLog\AuditEvent}
     * enum at the seam (it is the only place that knows the verb).
     */
    public static function crud(
        int $subjectId,
        string $subjectType,
        ?array $oldValues,
        ?array $newValues,
    ): self {
        return new self(
            subjectId: $subjectId,
            subjectType: $subjectType,
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }

    /**
     * Convenience factory for custom (non-CRUD) events. The `metadata`
     * bag is required for custom events; the CRUD factories take
     * values arrays instead.
     */
    public static function custom(
        int $subjectId,
        string $subjectType,
        array $metadata,
    ): self {
        return new self(
            subjectId: $subjectId,
            subjectType: $subjectType,
            metadata: $metadata,
        );
    }
}