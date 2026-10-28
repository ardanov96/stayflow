<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use App\Modules\Platform\Jobs\Middleware\PropagatesTenantContext;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializer;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Tests\Fixtures\Platform\FailingTenantJob;

/**
 * The tenant of an error report (task P1-16, `docs/PRODUCTION_READINESS.md`
 * OBS-01).
 *
 * An error that cannot be attributed to a tenant cannot be triaged: the
 * question is always "whose data is this?". The identifiers of the tenant
 * have to be in the event, and the data of the guest must not be.
 */
uses(RefreshDatabase::class);

it('attaches the tenant to an error raised in a tenant-scoped request', function (): void {
    $organization = Organization::factory()->create();

    $events = captureSentryEvents(function () use ($organization): void {
        app(OrganizationContext::class)->swap(
            TenantContext::forOrganizationAndProperty($organization->id, 42),
            fn (): mixed => app(ExceptionHandler::class)->report(new RuntimeException('boom')),
        );
    });

    expect($events)->toHaveCount(1)
        ->and($events[0]->getContexts()['tenant'] ?? [])->toBe([
            'organization_id' => $organization->id,
            'property_id' => 42,
        ]);
});

it('attaches the tenant to an error raised in a tenant-scoped job', function (): void {
    $organization = Organization::factory()->create();

    $events = captureSentryEvents(function () use ($organization): void {
        $job = new FailingTenantJob;

        // The tenant is active where the job is dispatched; the middleware
        // of the job propagates it to the worker, and the failure is
        // reported from inside it.
        app(OrganizationContext::class)->swap(
            TenantContext::forOrganizationAndProperty($organization->id, 42),
            function () use ($job): void {
                (new PropagatesTenantContext)->handle(
                    $job,
                    function (): void {
                        app(ExceptionHandler::class)->report(new RuntimeException('the job failed'));
                    },
                );
            },
        );
    });

    expect($events)->toHaveCount(1)
        ->and($events[0]->getContexts()['tenant'] ?? [])->toBe([
            'organization_id' => $organization->id,
            'property_id' => 42,
        ]);
});

it('attaches no tenant to an error raised outside a tenant', function (): void {
    $events = captureSentryEvents(function (): void {
        app(ExceptionHandler::class)->report(new RuntimeException('boom'));
    });

    expect($events)->toHaveCount(1)
        ->and($events[0]->getContexts())->not->toHaveKey('tenant');
});

it('never attaches the data of the guest to an error report', function (): void {
    $organization = Organization::factory()->create();

    // The identity is built at runtime: the source of the test is part of
    // the stack trace of the event, and a literal would be found there.
    $name = 'Wayan Sarta';
    $email = 'wayan.sarta.'.bin2hex(random_bytes(6)).'@example.test';

    $user = User::factory()->create([
        'name' => $name,
        'email' => $email,
    ]);

    Auth::login($user);

    $events = captureSentryEvents(function () use ($organization): void {
        app(OrganizationContext::class)->swap(
            TenantContext::forOrganizationAndProperty($organization->id, 42),
            fn (): mixed => app(ExceptionHandler::class)->report(new RuntimeException('boom')),
        );
    });

    expect($events)->toHaveCount(1);

    $user = $events[0]->getUser();
    $options = app(HubInterface::class)->getClient()?->getOptions() ?? new Options([]);
    $payload = (new PayloadSerializer($options))->serialize($events[0]);
    $serialized = (string) (is_array($payload) ? json_encode($payload) : $payload);

    // The Laravel integration of Sentry attaches the email and the name of
    // the logged-in user whatever `send_default_pii` says; the hook removes
    // them. The identifier of the user is enough to find the data again.
    expect($user?->getEmail())->toBeNull()
        ->and($user?->getUsername())->toBeNull()
        ->and($serialized)->not->toContain($name)
        ->and($serialized)->not->toContain($email);
});

/**
 * Capture the Sentry events raised while the callback runs.
 *
 * @param  callable(): void  $callback
 * @return list<Event>
 */
function captureSentryEvents(callable $callback): array
{
    $transport = new class implements TransportInterface
    {
        /** @var list<Event> */
        public array $events = [];

        public function send(Event $event): Result
        {
            $this->events[] = $event;

            return new Result(ResultStatus::success(), $event);
        }

        public function close(?int $timeout = null): Result
        {
            return new Result(ResultStatus::success());
        }
    };

    // The builder of the application carries the configured options (the
    // `before_send` hook of the tenant among them); only the transport is
    // replaced, so the events are captured instead of sent.
    $builder = clone app(ClientBuilder::class);

    $client = $builder
        ->setTransport($transport)
        ->getClient();

    app(HubInterface::class)->bindClient($client);

    $callback();

    return $transport->events;
}
