<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Application\Actions\CreatePropertyAction;
use App\Modules\Catalog\Application\Actions\UpdatePropertyAction;
use App\Modules\Catalog\Domain\Property\PropertyStatus;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\AuditLog;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();

    // Make user an Owner in this org
    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Owner,
    ]);

    $this->context = app(OrganizationContext::class);
});

it('creates a property using CreatePropertyAction and writes audit log', function (): void {
    $action = app(CreatePropertyAction::class);

    $property = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => $action->execute(
            organizationId: $this->org->id,
            data: [
                'name' => 'Sunset Beach Resort',
                'timezone' => 'Asia/Makassar',
                'currency' => 'IDR',
                'check_in_time' => '15:00',
                'check_out_time' => '12:00',
                'email' => 'contact@sunsetbeach.com',
                'phone' => '+628123456789',
            ],
            actorId: $this->user->id,
        ),
    );

    expect($property->id)->toBeGreaterThan(0)
        ->and($property->name)->toBe('Sunset Beach Resort')
        ->and($property->status)->toBe(PropertyStatus::Active)
        ->and($property->timezone)->toBe('Asia/Makassar')
        ->and($property->currency)->toBe('IDR');

    $audit = AuditLog::withoutGlobalScopes()->where('event', 'catalog.property.created')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->subject_id)->toBe($property->id)
        ->and($audit->new_values['name'])->toBe('Sunset Beach Resort');
});

it('updates a property using UpdatePropertyAction and writes audit log', function (): void {
    $property = Property::factory()->create([
        'organization_id' => $this->org->id,
        'name' => 'Old Name Resort',
    ]);

    $action = app(UpdatePropertyAction::class);
    $updated = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => $action->execute(
            property: $property,
            data: [
                'name' => 'New Name Resort',
                'status' => PropertyStatus::Inactive,
            ],
            actorId: $this->user->id,
        ),
    );

    expect($updated->name)->toBe('New Name Resort')
        ->and($updated->status)->toBe(PropertyStatus::Inactive);

    $audit = AuditLog::withoutGlobalScopes()->where('event', 'catalog.property.updated')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->old_values['name'])->toBe('Old Name Resort')
        ->and($audit->new_values['name'])->toBe('New Name Resort');
});

it('allows authorized owner to fetch and create properties via HTTP', function (): void {
    $response = $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->getJson('/api/catalog/properties');

    $response->assertOk();

    $createResponse = $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->postJson('/api/catalog/properties', [
            'name' => 'Uluwatu Luxury Cliff Villas',
            'timezone' => 'Asia/Makassar',
            'currency' => 'IDR',
        ]);

    $createResponse->assertCreated()
        ->assertJsonPath('data.name', 'Uluwatu Luxury Cliff Villas');
});

it('rejects unauthenticated requests with 401', function (): void {
    $this->getJson('/api/catalog/properties')->assertUnauthorized();
});

it('rejects unauthorized roles from updating properties with 403', function (): void {
    $housekeepingUser = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $housekeepingUser->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Housekeeping,
    ]);

    $property = Property::factory()->create(['organization_id' => $this->org->id]);

    $this->actingAs($housekeepingUser)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->patchJson("/api/catalog/properties/{$property->id}", [
            'name' => 'Hacked Name',
        ])
        ->assertForbidden();
});
