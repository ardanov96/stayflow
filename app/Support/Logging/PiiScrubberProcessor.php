<?php

declare(strict_types=1);

namespace App\Support\Logging;

use App\Support\Helpers\PiiScrubber;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that scrubs emails, phone numbers, credentials and JWTs out
 * of the message, context, and extra fields of every record before it is handed
 * to the formatter. Paired with the `json` log channel so structured output is
 * safe to ship to log aggregators without a second PII review pass.
 */
final class PiiScrubberProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: PiiScrubber::scrubString($record->message),
            context: PiiScrubber::scrubArray($record->context),
            extra: PiiScrubber::scrubArray($record->extra),
        );
    }
}
