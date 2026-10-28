<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Contracts\SubscriptionRepository;
use App\Modules\Platform\Domain\Subscription\Subscription;
use App\Modules\Platform\Domain\Subscription\SubscriptionStatus;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gate of the modules of the plan.
 *
 * A module that the organization does not pay for is not reachable, by
 * route and by API: the module is the second segment of the URL of the
 * API (`api/<module>/...`), so one middleware covers both.
 */
final class EnsureModuleEnabled
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly OrganizationContext $organizationContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $module = $this->moduleOf($request);

        if ($module === null) {
            return $next($request);
        }

        $organizationId = $this->organizationContext->current()?->organizationId;

        if ($organizationId === null) {
            // Without a tenant there is no plan to check: the request is
            // not a tenant request, and it is refused by the tenant
            // scope anyway.
            return $next($request);
        }

        $subscription = $this->subscriptions->find($organizationId);

        $subscription ??= $this->trialOf($organizationId);

        if (! $subscription->hasModule($module)) {
            abort(403, sprintf('The module "%s" is not part of your plan.', $module));
        }

        return $next($request);
    }

    /**
     * The trial of an organization that has no subscription yet.
     *
     * The trial is counted from the day the organization was created, so
     * it ends even when nobody ever opened the billing page.
     */
    private function trialOf(int $organizationId): Subscription
    {
        $organization = Organization::query()->find($organizationId);

        $trialDays = (int) config('plans.trial_days', 14);

        return new Subscription(
            organizationId: $organizationId,
            plan: (string) config('plans.default_plan', 'pms'),
            status: SubscriptionStatus::Trialing,
            trialEndsAt: $organization?->created_at?->toImmutable()?->modify("+{$trialDays} days"),
            gateway: (string) config('plans.default_gateway', 'xendit'),
        );
    }

    private function moduleOf(Request $request): ?string
    {
        $segments = $request->segments();
        $position = array_search('api', $segments, true);

        if ($position === false) {
            return null;
        }

        $module = $segments[$position + 1] ?? null;

        return is_string($module) && $module !== '' ? $module : null;
    }
}
