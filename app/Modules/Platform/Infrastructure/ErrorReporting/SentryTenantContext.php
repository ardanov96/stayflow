<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\ErrorReporting;

use App\Modules\Platform\Contracts\OrganizationContext;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\UserDataBag;

/**
 * Attaches the tenant to a Sentry event.
 *
 * An error that cannot be attributed to a tenant cannot be triaged: the
 * question is always "whose data is this?". The hook answers it with the
 * two identifiers and nothing else.
 *
 * **No guest data is attached.** Not the name, not the email, not the
 * document number: those are the fields of the guest, and an error
 * report is not the place for them. The identifiers of the tenant are
 * enough to find the data.
 *
 * The hook runs when the event is sent, not when it is raised, so it
 * sees the tenant that is active at that moment: the tenant of the
 * request, or the tenant the job middleware propagated. A system job
 * without a tenant attaches nothing, and it never inherits the tenant of
 * a previous job.
 */
final class SentryTenantContext
{
    /**
     * The `before_send` hook of the Sentry client.
     *
     * It does two things: it attaches the tenant, and it takes the
     * identity of the user out of the event.
     */
    public static function attach(Event $event, ?EventHint $hint): Event
    {
        self::removePersonalData($event);

        $context = app(OrganizationContext::class)->current();

        if ($context === null) {
            return $event;
        }

        $event->setContext('tenant', [
            'organization_id' => $context->organizationId,
            'property_id' => $context->propertyId,
        ]);

        return $event;
    }

    /**
     * Takes the identity of the user out of the event.
     *
     * The Laravel integration of Sentry attaches the email and the name
     * of the logged-in user to the scope **whatever `send_default_pii`
     * says** — the setting only covers the request data. An error report
     * is not the place for the identity of a guest, and the identifier of
     * the user is enough to find the data again.
     */
    private static function removePersonalData(Event $event): void
    {
        $user = $event->getUser();

        if ($user === null) {
            return;
        }

        // Only the identifier of the user is kept: the email, the name and
        // the address are the identity of the person, and an error report
        // does not need it.
        $event->setUser(new UserDataBag($user->getId()));
    }
}
