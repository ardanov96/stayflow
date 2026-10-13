<?php

declare(strict_types=1);

namespace App\Modules\Platform\Logging;

use App\Modules\Platform\Contracts\OrganizationContext;
use Monolog\LogRecord;

/**
 * Monolog processor that promotes the active tenant ids into every
 * log record's `extra` array. Closes the deferred P0-07 follow-up
 * that asked for organization_id and property_id in the structured
 * JSON log channel (ARCHITECTURE.md §15).
 *
 * Behavior:
 * - With an active context, the record gains
 *     extra.organization_id = <int>
 *     extra.property_id      = <int|null>
 * - With no context, the record is returned unchanged. (System
 *   jobs that span tenants log without these fields, by design.)
 *
 * Property_id is only added when the context has one. A request
 * scoped to the whole organization logs organization_id only; a
 * request scoped to a specific property logs both.
 *
 * Tenant ids are integers and never contain PII, so the
 * PiiScrubberProcessor is not involved here.
 */
final class TenantLogProcessor
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $current = $this->context->current();
        if ($current === null) {
            return $record;
        }

        $extra = $record->extra;
        $extra['organization_id'] = $current->organizationId;
        if ($current->propertyId !== null) {
            $extra['property_id'] = $current->propertyId;
        }

        return $record->with(extra: $extra);
    }
}
