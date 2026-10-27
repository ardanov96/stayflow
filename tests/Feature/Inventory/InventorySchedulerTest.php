<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Inventory\Infrastructure\Jobs\ExpireHoldsJob;
use App\Modules\Inventory\Infrastructure\Jobs\MaterializeInventoryDaysJob;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->property = Property::factory()->create(['organization_id' => $this->org->id]);
});

it('dispatches one hold-expiry job per organization', function (): void {
    Queue::fake();

    Organization::factory()->count(2)->create();

    $this->artisan('inventory:expire-holds')
        ->expectsOutput('Dispatched 3 hold-expiry job(s).')
        ->assertExitCode(0);

    Queue::assertPushed(ExpireHoldsJob::class, 3);
    Queue::assertPushed(
        ExpireHoldsJob::class,
        fn (ExpireHoldsJob $job): bool => $job->organizationId === $this->org->id,
    );
});

it('dispatches one materialization job per property', function (): void {
    Queue::fake();

    $otherOrg = Organization::factory()->create();
    Property::factory()->create(['organization_id' => $otherOrg->id]);

    $this->artisan('inventory:materialize-days')
        ->expectsOutput('Dispatched 2 materialization job(s).')
        ->assertExitCode(0);

    Queue::assertPushed(MaterializeInventoryDaysJob::class, 2);
    Queue::assertPushed(
        MaterializeInventoryDaysJob::class,
        fn (MaterializeInventoryDaysJob $job): bool => $job->propertyId === $this->property->id
            && $job->organizationId === $this->org->id,
    );
});

it('schedules the hold expiry and the inventory materialization', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('inventory:expire-holds')
        ->expectsOutputToContain('inventory:materialize-days')
        ->assertExitCode(0);
});
