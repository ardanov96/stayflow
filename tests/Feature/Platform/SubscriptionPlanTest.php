<?php

declare(strict_types=1);

use App\Modules\Platform\Application\Billing\ProcessBillingEvent;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Contracts\SubscriptionRepository;
use App\Modules\Platform\Domain\Subscription\Subscription;
use App\Modules\Platform\Domain\Subscription\SubscriptionStatus;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Http\Middleware\EnsureModuleEnabled;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The plan of the organization (task P1-11).
 */
uses(RefreshDatabase::class);

function subscribe(int $organizationId, string $plan = 'pms', SubscriptionStatus $status = SubscriptionStatus::Active, string $gateway = 'xendit'): Subscription
{
    $subscription = new Subscription(
        organizationId: $organizationId,
        plan: $plan,
        status: $status,
        trialEndsAt: $status === SubscriptionStatus::Trialing ? new DateTimeImmutable('+14 days') : null,
        gateway: $gateway,
        gatewayReference: 'org-'.$organizationId,
    );

    app(SubscriptionRepository::class)->save($subscription);

    return $subscription;
}

it('refuses a module that is not part of the plan', function (): void {
    $organization = Organization::factory()->create();

    subscribe($organization->id, plan: 'pms');

    $middleware = new EnsureModuleEnabled(
        app(SubscriptionRepository::class),
        app(OrganizationContext::class),
    );

    $context = app(OrganizationContext::class);

    $context->swap(
        TenantContext::forOrganization($organization->id),
        function () use ($middleware): void {
            // The catalog is part of the plan of the PMS.
            $response = $middleware->handle(
                Request::create('/api/catalog/properties'),
                fn () => response('ok'),
            );

            expect($response->getContent())->toBe('ok');

            // The distribution is not.
            expect(fn () => $middleware->handle(
                Request::create('/api/distribution/channels'),
                fn () => response('ok'),
            ))->toThrow(HttpException::class);
        },
    );
});

it('refuses every module when the subscription is over', function (): void {
    $organization = Organization::factory()->create();

    subscribe($organization->id, plan: 'pms', status: SubscriptionStatus::Expired);

    $middleware = new EnsureModuleEnabled(
        app(SubscriptionRepository::class),
        app(OrganizationContext::class),
    );

    app(OrganizationContext::class)->swap(
        TenantContext::forOrganization($organization->id),
        function () use ($middleware): void {
            expect(fn () => $middleware->handle(
                Request::create('/api/catalog/properties'),
                fn () => response('ok'),
            ))->toThrow(HttpException::class);
        },
    );
});

it('leaves the routes that are not a module alone', function (): void {
    $organization = Organization::factory()->create();

    subscribe($organization->id, plan: 'pms');

    $middleware = new EnsureModuleEnabled(
        app(SubscriptionRepository::class),
        app(OrganizationContext::class),
    );

    $response = $middleware->handle(
        Request::create('/shell'),
        fn () => response('ok'),
    );

    expect($response->getContent())->toBe('ok');
});

it('knows that a trial is over', function (): void {
    $organization = Organization::factory()->create();

    $subscription = subscribe(
        $organization->id,
        status: SubscriptionStatus::Trialing,
    );

    expect($subscription->trialExpired(new DateTimeImmutable('+15 days')))->toBeTrue()
        ->and($subscription->trialExpired(new DateTimeImmutable('+13 days')))->toBeFalse()
        ->and($subscription->grantsAccess())->toBeTrue();
});

it('makes the subscription past due when a cycle fails', function (): void {
    $organization = Organization::factory()->create();

    subscribe($organization->id, status: SubscriptionStatus::Active);

    $outcome = app(ProcessBillingEvent::class)->handle('org-'.$organization->id, [
        'business_id' => 'test-business',
        'data' => [
            'business_id' => 'test-business',
            'data' => [
                'status' => 'FAILED',
                'reference_id' => 'period-2026-10',
                'total_amount' => 1500000,
                'failure_code' => 'INSUFFICIENT_BALANCE',
            ],
        ],
    ]);

    expect($outcome->value)->toBe('handled');

    $subscription = app(SubscriptionRepository::class)->find($organization->id);

    expect($subscription?->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription?->grantsAccess())->toBeTrue();
});

it('does not charge twice when the provider delivers the event twice', function (): void {
    $organization = Organization::factory()->create();

    subscribe($organization->id, status: SubscriptionStatus::Active);

    $payload = [
        'business_id' => 'test-business',
        'data' => [
            'business_id' => 'test-business',
            'data' => [
                'status' => 'SUCCEEDED',
                'reference_id' => 'period-2026-10',
                'total_amount' => 1500000,
            ],
        ],
    ];

    $first = app(ProcessBillingEvent::class)->handle('org-'.$organization->id, $payload);
    $second = app(ProcessBillingEvent::class)->handle('org-'.$organization->id, $payload);

    expect($first->value)->toBe('handled')
        ->and($second->value)->toBe('replayed');

    $invoices = app(SubscriptionRepository::class)->invoicesFor($organization->id);

    expect($invoices)->toHaveCount(1)
        ->and($invoices[0]['status'])->toBe('paid')
        ->and((int) $invoices[0]['amount_minor'])->toBe(1500000);
});

it('speaks to the gateway the organization chose', function (): void {
    $organization = Organization::factory()->create();

    subscribe($organization->id, status: SubscriptionStatus::Active, gateway: 'midtrans');

    $outcome = app(ProcessBillingEvent::class)->handle('org-'.$organization->id, [
        'order_id' => 'period-2026-10',
        'transaction_status' => 'CAPTURE',
        'gross_amount' => 1500000,
    ]);

    expect($outcome->value)->toBe('handled');

    $invoices = app(SubscriptionRepository::class)->invoicesFor($organization->id);

    expect($invoices)->toHaveCount(1)
        ->and($invoices[0]['status'])->toBe('paid');
});
