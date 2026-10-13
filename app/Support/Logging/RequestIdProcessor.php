<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Promotes the per-request id (set by the AssignRequestId middleware via
 * Log::shareContext) from the record's `context` to the `extra` bag, so log
 * search tools that index top-level fields can filter on request_id without
 * nesting into context. Skips if extra already has one.
 */
final class RequestIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $requestId = $record->context['request_id'] ?? null;
        if (! is_string($requestId) || $requestId === '' || isset($record->extra['request_id'])) {
            return $record;
        }

        $extra = $record->extra;
        $extra['request_id'] = $requestId;

        return $record->with(extra: $extra);
    }
}
