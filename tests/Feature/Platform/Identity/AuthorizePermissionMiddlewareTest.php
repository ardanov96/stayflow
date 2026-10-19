<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\AuditLog;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();
});

it('returns 401 when unauthenticated user hits guarded route', function (): void {
    $response = $this->withHeaders([
        'X-Organization-Id' => (string) $this->org->id,
    ])->getJson(route('testing.rbac.reservations'));

    $response->assertStatus(401);
});

it('returns 403, writes structured log, and records auth.forbidden audit event when unauthorized', function (): void {
    Log::spy();

    $response = $this->actingAs($this->user)->withHeaders([
        'X-Organization-Id' => (string) $this->org->id,
    ])->getJson(route('testing.rbac.reservations'));

    $response->assertStatus(403);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Forbidden: user lacks required permission', Mockery::subset([
            'user_id' => $this->user->id,
            'organization_id' => $this->org->id,
            'permission' => 'reservations.create',
            'path' => '_testing/rbac/reservations',
        ]));

    $audit = AuditLog::withoutGlobalScopes()
        ->where('event', 'auth.forbidden')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) $audit->organization_id)->toBe($this->org->id)
        ->and((int) $audit->subject_id)->toBe($this->user->id)
        ->and($audit->metadata['permission'])->toBe('reservations.create')
        ->and($audit->metadata['path'])->toBe('_testing/rbac/reservations');
});

it('allows authorized roles and forbids unauthorized roles across route endpoints', function (
    Role $role,
    string $route,
    int $expectedStatus,
): void {
    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => $role->value,
    ]);

    $response = $this->actingAs($this->user)->withHeaders([
        'X-Organization-Id' => (string) $this->org->id,
    ])->getJson(route($route));

    $response->assertStatus($expectedStatus);
})->with([
    'Owner can access reservations' => [Role::Owner, 'testing.rbac.reservations', 200],
    'Owner can access audit' => [Role::Owner, 'testing.rbac.audit', 200],
    'Manager can access reservations' => [Role::Manager, 'testing.rbac.reservations', 200],
    'Manager forbidden on audit' => [Role::Manager, 'testing.rbac.audit', 403],
    'FrontDesk can access reservations' => [Role::FrontDesk, 'testing.rbac.reservations', 200],
    'FrontDesk forbidden on folio refund' => [Role::FrontDesk, 'testing.rbac.folio-refund', 403],
    'Housekeeping forbidden on reservations' => [Role::Housekeeping, 'testing.rbac.reservations', 403],
    'Housekeeping can access housekeeping' => [Role::Housekeeping, 'testing.rbac.housekeeping', 200],
    'Revenue can access rates' => [Role::Revenue, 'testing.rbac.rates', 200],
    'Revenue forbidden on reservations create' => [Role::Revenue, 'testing.rbac.reservations', 403],
    'Marketing can access promos' => [Role::Marketing, 'testing.rbac.promos', 200],
    'Marketing forbidden on rates' => [Role::Marketing, 'testing.rbac.rates', 403],
]);

it('denies user belonging to a different organization', function (): void {
    $otherOrg = Organization::factory()->create();

    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $otherOrg->id,
        'property_id' => null,
        'role' => Role::Owner->value,
    ]);

    // Request is made targeting $this->org, where user is NOT a member
    $response = $this->actingAs($this->user)->withHeaders([
        'X-Organization-Id' => (string) $this->org->id,
    ])->getJson(route('testing.rbac.reservations'));

    $response->assertStatus(403);
});

it('wires Gate::allows() to the active tenant context and RoleChecker', function (): void {
    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Manager->value,
    ]);

    $contextManager = app(OrganizationContext::class);

    $contextManager->swap(TenantContext::forOrganization($this->org->id), function (): void {
        expect(Gate::forUser($this->user)->allows('reservations.create'))->toBeTrue()
            ->and(Gate::forUser($this->user)->allows('audit.read'))->toBeFalse()
            ->and(Gate::forUser($this->user)->allows('unknown.ability'))->toBeFalse();
    });
});
