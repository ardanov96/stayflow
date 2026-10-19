<?php

declare(strict_types=1);

use App\Modules\Platform\Domain\Identity\Permission;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Identity\RolePermissionMatrix;

it('returns the same instance on repeated calls (sealed singleton)', function (): void {
    expect(RolePermissionMatrix::instance())->toBe(RolePermissionMatrix::instance());
});

it('every role has an entry in the matrix (no missing roles)', function (): void {
    $matrix = RolePermissionMatrix::instance();
    foreach (Role::all() as $role) {
        // permissionsFor() throws on a missing role; success here is
        // the assertion.
        expect($matrix->permissionsFor($role))->not->toBeEmpty();
    }
});

it('the matrix contains no duplicates per role', function (): void {
    $matrix = RolePermissionMatrix::instance();
    foreach (Role::all() as $role) {
        $values = array_map(
            static fn (Permission $p): string => $p->value,
            $matrix->permissionsFor($role),
        );
        expect(array_unique($values))->toHaveCount(count($values));
    }
});

it('the matrix only references known permissions (no stale entries)', function (): void {
    $matrix = RolePermissionMatrix::instance();
    $known = array_map(
        static fn (Permission $p): string => $p->value,
        Permission::all(),
    );
    foreach (Role::all() as $role) {
        foreach ($matrix->permissionsFor($role) as $permission) {
            expect($known)->toContain($permission->value);
        }
    }
});

it('Owner has the superset of permissions granted to Manager', function (): void {
    // The matrix MUST keep Owner >= Manager; if a future Manager
    // permission is added without also adding it to Owner, that's a
    // contract violation caught here.
    $matrix = RolePermissionMatrix::instance();
    $owner = $matrix->permissionsFor(Role::Owner);
    $manager = $matrix->permissionsFor(Role::Manager);
    foreach ($manager as $permission) {
        expect($owner)->toContain($permission);
    }
});

it('allows() returns true iff the permission is on the role list', function (): void {
    $matrix = RolePermissionMatrix::instance();
    foreach (Role::all() as $role) {
        foreach (Permission::all() as $permission) {
            $expected = in_array($permission, $matrix->permissionsFor($role), true);
            expect($matrix->allows($role, $permission))->toBe($expected);
        }
    }
});

it('Owner has AuditRead, BillingManage, and SettingsManage', function (): void {
    $matrix = RolePermissionMatrix::instance();
    expect($matrix->allows(Role::Owner, Permission::AuditRead))->toBeTrue();
    expect($matrix->allows(Role::Owner, Permission::BillingManage))->toBeTrue();
    expect($matrix->allows(Role::Owner, Permission::SettingsManage))->toBeTrue();
});

it('Manager has SettingsManage but not AuditRead or BillingManage', function (): void {
    $matrix = RolePermissionMatrix::instance();
    expect($matrix->allows(Role::Manager, Permission::SettingsManage))->toBeTrue();
    expect($matrix->allows(Role::Manager, Permission::AuditRead))->toBeFalse();
    expect($matrix->allows(Role::Manager, Permission::BillingManage))->toBeFalse();
});

it('FrontDesk can check in/out reservations and charge the folio', function (): void {
    $matrix = RolePermissionMatrix::instance();
    expect($matrix->allows(Role::FrontDesk, Permission::ReservationsCheckIn))->toBeTrue();
    expect($matrix->allows(Role::FrontDesk, Permission::ReservationsCheckOut))->toBeTrue();
    expect($matrix->allows(Role::FrontDesk, Permission::FolioCharge))->toBeTrue();
    // but cannot void a charge
    expect($matrix->allows(Role::FrontDesk, Permission::FolioRefund))->toBeFalse();
    expect($matrix->allows(Role::FrontDesk, Permission::PaymentsRefund))->toBeFalse();
});

it('Housekeeping cannot touch rates or reservations (only room status)', function (): void {
    $matrix = RolePermissionMatrix::instance();
    expect($matrix->allows(Role::Housekeeping, Permission::HousekeepingManage))->toBeTrue();
    expect($matrix->allows(Role::Housekeeping, Permission::RatePlansManage))->toBeFalse();
    expect($matrix->allows(Role::Housekeeping, Permission::ReservationsCreate))->toBeFalse();
    expect($matrix->allows(Role::Housekeeping, Permission::FolioCharge))->toBeFalse();
});

it('Revenue cannot charge the folio or manage users', function (): void {
    $matrix = RolePermissionMatrix::instance();
    expect($matrix->allows(Role::Revenue, Permission::RatePlansManage))->toBeTrue();
    expect($matrix->allows(Role::Revenue, Permission::InventoryManage))->toBeTrue();
    expect($matrix->allows(Role::Revenue, Permission::UsersInvite))->toBeFalse();
    expect($matrix->allows(Role::Revenue, Permission::FolioCharge))->toBeFalse();
});

it('Marketing cannot touch reservations or rates', function (): void {
    $matrix = RolePermissionMatrix::instance();
    expect($matrix->allows(Role::Marketing, Permission::PromosManage))->toBeTrue();
    expect($matrix->allows(Role::Marketing, Permission::GuestsWrite))->toBeTrue();
    expect($matrix->allows(Role::Marketing, Permission::ReservationsCreate))->toBeFalse();
    expect($matrix->allows(Role::Marketing, Permission::RatePlansManage))->toBeFalse();
    expect($matrix->allows(Role::Marketing, Permission::FolioRefund))->toBeFalse();
});
