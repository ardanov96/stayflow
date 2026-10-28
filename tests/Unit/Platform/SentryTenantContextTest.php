<?php

declare(strict_types=1);

use App\Modules\Platform\Infrastructure\ErrorReporting\SentryTenantContext;
use Sentry\Event;
use Sentry\UserDataBag;

/**
 * The hook of the error report (task P1-16).
 *
 * The Laravel integration of Sentry attaches the email and the name of
 * the logged-in user to the scope. The hook takes the identity out: an
 * error report is not the place for the identity of a guest, and the
 * identifier of the user is enough to find the data again.
 */
it('takes the identity of the user out of the event', function (): void {
    $event = Event::createEvent();

    $user = new UserDataBag(42);
    $user->setEmail('wayan.sarta@example.test');
    $user->setUsername('wayan.sarta');
    $user->setIpAddress('203.0.113.7');

    $event->setUser($user);

    SentryTenantContext::attach($event, null);

    $user = $event->getUser();

    expect($user)->not->toBeNull()
        ->and($user->getId())->toBe(42)
        ->and($user->getEmail())->toBeNull()
        ->and($user->getUsername())->toBeNull()
        ->and($user->getIpAddress())->toBeNull();
});

it('leaves an event without a user alone', function (): void {
    $event = Event::createEvent();

    SentryTenantContext::attach($event, null);

    expect($event->getUser())->toBeNull();
});
