<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Domain\Identity\Permission;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Infrastructure\Identity\EloquentRoleChecker;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->checker = new EloquentRoleChecker;
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();
});

it('returns false for user with no memberships in the organization', function (): void {
    expect($this->checker->hasRole($this->user, $this->org->id, null, Role::Owner))->toBeFalse()
        ->and($this->checker->hasPermission($this->user, $this->org->id, null, Permission::AuditRead))->toBeFalse();
});

it('honors org-wide membership across all properties', function (): void {
    $prop = Property::factory()->create(['organization_id' => $this->org->id]);

    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Owner->value,
    ]);

    expect($this->checker->hasRole($this->user, $this->org->id, null, Role::Owner))->toBeTrue()
        ->and($this->checker->hasRole($this->user, $this->org->id, $prop->id, Role::Owner))->toBeTrue()
        ->and($this->checker->hasRole($this->user, $this->org->id, null, Role::Manager))->toBeFalse()
        ->and($this->checker->hasPermission($this->user, $this->org->id, $prop->id, Permission::AuditRead))->toBeTrue();
});

it('scopes property-specific role to that property only', function (): void {
    $prop1 = Property::factory()->create(['organization_id' => $this->org->id]);
    $prop2 = Property::factory()->create(['organization_id' => $this->org->id]);

    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => $prop1->id,
        'role' => Role::FrontDesk->value,
    ]);

    // Matching property
    expect($this->checker->hasRole($this->user, $this->org->id, $prop1->id, Role::FrontDesk))->toBeTrue()
        ->and($this->checker->hasPermission($this->user, $this->org->id, $prop1->id, Permission::ReservationsCheckIn))->toBeTrue()
        ->and($this->checker->hasPermission($this->user, $this->org->id, $prop1->id, Permission::FolioRefund))->toBeFalse();

    // Different property: denied
    expect($this->checker->hasRole($this->user, $this->org->id, $prop2->id, Role::FrontDesk))->toBeFalse()
        ->and($this->checker->hasPermission($this->user, $this->org->id, $prop2->id, Permission::ReservationsCheckIn))->toBeFalse();

    // Org-scoped check ($propertyId === null): accepts any active membership in the org
    expect($this->checker->hasRole($this->user, $this->org->id, null, Role::FrontDesk))->toBeTrue()
        ->and($this->checker->hasPermission($this->user, $this->org->id, null, Permission::ReservationsCheckIn))->toBeTrue();
});

it('ignores soft-deleted memberships', function (): void {
    $membership = Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Owner->value,
    ]);
    $membership->delete();

    expect($this->checker->hasRole($this->user, $this->org->id, null, Role::Owner))->toBeFalse()
        ->and($this->checker->hasPermission($this->user, $this->org->id, null, Permission::AuditRead))->toBeFalse();
});

it('membershipsIn returns only memberships for the specified user and organization', function (): void {
    $prop = Property::factory()->create(['organization_id' => $this->org->id]);
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create();

    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => $prop->id,
        'role' => Role::Manager->value,
    ]);
    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $otherOrg->id,
        'property_id' => null,
        'role' => Role::Owner->value,
    ]);
    Membership::factory()->create([
        'user_id' => $otherUser->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Owner->value,
    ]);

    $results = $this->checker->membershipsIn($this->user, $this->org->id);

    expect($results)->toHaveCount(1)
        ->and((int) $results->first()->property_id)->toBe($prop->id)
        ->and($results->first()->roleEnum())->toBe(Role::Manager);
});
