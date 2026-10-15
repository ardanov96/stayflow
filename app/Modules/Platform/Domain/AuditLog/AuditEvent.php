<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\AuditLog;

use InvalidArgumentException;

/**
 * The set of CRUD-shaped event codes the audit log records
 * automatically when an {@see \App\Modules\Platform\Infrastructure\Concerns\Auditable}
 * model is created, updated, deleted, or restored. Custom events
 * (e.g. `rate.bulk.updated`, `member.role.changed`) are passed as
 * free-form strings through {@see \App\Modules\Platform\Application\AuditLog\Ports\Auditor::recordCustom}
 * and are NOT represented here; this enum only covers the four
 * automatic verbs the Eloquent observer fires.
 *
 * Pure PHP, no Eloquent, no Laravel. Lives under `Domain/` so the
 * `tests/Arch/EloquentBoundariesTest` rule (Domain/Application never
 * import `Illuminate\Database\Eloquent\Model`) is satisfied.
 */
enum AuditEvent: string
{
    case Created  = 'created';
    case Updated  = 'updated';
    case Deleted  = 'deleted';
    case Restored = 'restored';

    /**
     * Construct from a string, with a clear error for unknown values.
     * Use this at the seam between the `Auditor` port (which takes an
     * `AuditEvent`) and a stored column / API payload (which is a
     * string).
     */
    public static function fromString(string $value): self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }
        throw new InvalidArgumentException(sprintf(
            'Unknown audit event "%s"; expected one of: %s.',
            $value,
            implode(', ', array_map(static fn (self $c): string => $c->value, self::cases())),
        ));
    }

    /**
     * True for events that come from an Eloquent model observer.
     * False for events that have to be written explicitly (e.g. custom
     * domain events that are not CRUD-shaped).
     */
    public function isAutoRecorded(): bool
    {
        return true;
    }
}