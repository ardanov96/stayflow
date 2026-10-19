<?php

declare(strict_types=1);

use App\Modules\Platform\Domain\Identity\Permission;

it('every permission uses the {module}.{action} naming convention', function (): void {
    foreach (Permission::all() as $permission) {
        expect($permission->value)
            ->toMatch('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/');
    }
});

it('fromString() throws on unknown value with a clear error', function (): void {
    Permission::fromString('not.a.permission');
})->throws(InvalidArgumentException::class, 'Unknown permission "not.a.permission"');

it('fromString() returns the matching case', function (): void {
    expect(Permission::fromString('users.invite'))->toBe(Permission::UsersInvite);
    expect(Permission::fromString('audit.read'))->toBe(Permission::AuditRead);
});

it('module() extracts the module prefix', function (string $value, string $expected): void {
    expect(Permission::fromString($value)->module())->toBe($expected);
})->with([
    'users.invite' => ['users.invite', 'users'],
    'reservations.create' => ['reservations.create', 'reservations'],
    'audit.read' => ['audit.read', 'audit'],
    'booking_engine.read' => ['booking_engine.read', 'booking_engine'],
    'rate_plans.manage' => ['rate_plans.manage', 'rate_plans'],
]);

it('the module prefix is unique per permission in the all() list', function (): void {
    // Guard against a typo where two permissions share a wire string.
    $values = array_map(static fn (Permission $p): string => $p->value, Permission::all());
    expect(array_unique($values))->toHaveCount(count($values));
});
