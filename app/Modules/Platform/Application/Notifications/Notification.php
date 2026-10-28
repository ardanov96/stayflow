<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Notifications;

/**
 * A notification to deliver.
 *
 * It carries the template (the mailable), the locale it has to be
 * rendered in, and the recipients. The channel and the delivery log know
 * nothing else about it: a WhatsApp notification is the same object with
 * another channel.
 */
final readonly class Notification
{
    /**
     * @param  array<string, mixed>  $arguments  The constructor arguments of the mailable, by name.
     * @param  list<string>  $recipients
     */
    public function __construct(
        public int $organizationId,
        public ?int $propertyId,
        public string $channel,
        public string $mailable,
        public string $locale,
        public array $arguments,
        public array $recipients,
    ) {}
}
