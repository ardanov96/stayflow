<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Inventory\Infrastructure\Jobs\ExpireHoldsJob;
use App\Modules\Inventory\Infrastructure\Jobs\MaterializeInventoryDaysJob;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('inventory:expire-holds', function (): void {
    $organizationIds = Organization::query()->pluck('id');

    foreach ($organizationIds as $organizationId) {
        ExpireHoldsJob::dispatch((int) $organizationId);
    }

    $this->info(sprintf('Dispatched %d hold-expiry job(s).', $organizationIds->count()));
})->purpose('Expire the inventory holds whose time-to-live has passed');

Artisan::command('inventory:materialize-days', function (OrganizationContext $context): void {
    $dispatched = 0;

    foreach (Organization::query()->pluck('id') as $organizationId) {
        $organizationId = (int) $organizationId;

        // The property list is tenant scoped: it is only visible inside
        // the organization context.
        $context->swap(TenantContext::forOrganization($organizationId), function () use ($organizationId, &$dispatched): void {
            foreach (Property::query()->get() as $property) {
                MaterializeInventoryDaysJob::dispatch(
                    propertyId: (int) $property->id,
                    organizationId: $organizationId,
                );

                $dispatched++;
            }
        });
    }

    $this->info(sprintf('Dispatched %d materialization job(s).', $dispatched));
})->purpose('Materialize the inventory day rows of every property');
