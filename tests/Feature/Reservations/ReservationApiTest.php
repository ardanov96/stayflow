<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\RoomType;
use App\Modules\Inventory\Contracts\InventoryService;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Inventory\Infrastructure\Models\RatePrice;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The endpoint of the staff (task P2-02).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();
    $this->context = app(OrganizationContext::class);

    $this->property = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => Property::factory()->create(['organization_id' => $this->org->id]),
    );

    $this->roomType = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RoomType::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'total_units' => 2,
        ]),
    );

    $this->ratePlan = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => RatePlan::factory()->create([
            'organization_id' => $this->org->id,
            'property_id' => $this->property->id,
            'code' => 'BAR',
            'name' => 'Best Available Rate',
        ]),
    );

    foreach (['2026-11-01', '2026-11-02', '2026-11-03'] as $date) {
        $this->context->swap(
            TenantContext::forOrganization($this->org->id),
            fn () => RatePrice::factory()->create([
                'organization_id' => $this->org->id,
                'property_id' => $this->property->id,
                'rate_plan_id' => $this->ratePlan->id,
                'room_type_id' => $this->roomType->id,
                'date' => $date,
                'base_price_minor' => 500_000,
                'currency' => 'IDR',
                'extra_adult_price_minor' => 100_000,
                'extra_child_price_minor' => 50_000,
            ]),
        );
    }

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $this->property->id),
        fn () => app(InventoryService::class)->materializeDays(
            propertyId: $this->property->id,
            startDate: '2026-11-01',
            endDate: '2026-11-05',
        ),
    );
});

function reservationPayload(object $test): array
{
    return [
        'property_id' => $test->property->id,
        'room_type_id' => $test->roomType->id,
        'rate_plan_id' => $test->ratePlan->id,
        'arrival' => '2026-11-01',
        'departure' => '2026-11-04',
        'adults' => 2,
        'children' => 0,
        'guest_id' => 7,
        'idempotency_key' => 'api-key-1',
    ];
}

it('refuses a reservation from a user without the permission', function (): void {
    $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->withHeader('X-Property-Id', (string) $this->property->id);

    $this->postJson('/api/reservations', reservationPayload($this))->assertForbidden();
});

it('creates a reservation through the endpoint', function (): void {
    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Owner,
    ]);

    $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->withHeader('X-Property-Id', (string) $this->property->id);

    $response = $this->postJson('/api/reservations', reservationPayload($this));

    $response->assertCreated()->assertJsonPath('status', 'confirmed');

    expect($response->json('code'))->toStartWith('RSV-');
});

it('refuses a request without the idempotency key', function (): void {
    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Owner,
    ]);

    $this->actingAs($this->user);

    $payload = reservationPayload($this);
    unset($payload['idempotency_key']);

    $this->withHeader('X-Organization-Id', (string) $this->org->id)
        ->withHeader('X-Property-Id', (string) $this->property->id)
        ->postJson('/api/reservations', $payload)
        ->assertJsonValidationErrors('idempotency_key');
});
