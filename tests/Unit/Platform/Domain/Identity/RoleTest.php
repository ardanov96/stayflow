<?php

declare(strict_types=1);

use App\Modules\Platform\Domain\Identity\Role;

it('has the six pilot roles in stable order', function (): void {
    expect(Role::all())->toBe([
        Role::Owner,
        Role::Manager,
        Role::FrontDesk,
        Role::Housekeeping,
        Role::Revenue,
        Role::Marketing,
    ]);
});

it('exposes the wire string used on memberships.role', function (string $value): void {
    expect(Role::from($value)->value)->toBe($value);
})->with([
    'owner' => ['owner'],
    'manager' => ['manager'],
    'front_desk' => ['front_desk'],
    'housekeeping' => ['housekeeping'],
    'revenue' => ['revenue'],
    'marketing' => ['marketing'],
]);

it('every role value fits the 32-byte memberships.role column', function (): void {
    // The memberships.role column is VARCHAR(32). A future role name
    // that exceeds 32 bytes would truncate silently; the test makes
    // the constraint a checked invariant.
    foreach (Role::all() as $role) {
        expect(strlen($role->value))->toBeLessThanOrEqual(32);
    }
});

it('fromString() throws on unknown value with a clear error', function (): void {
    Role::fromString('not_a_role');
})->throws(InvalidArgumentException::class, 'Unknown role "not_a_role"');

it('fromString() returns the matching case', function (): void {
    expect(Role::fromString('owner'))->toBe(Role::Owner);
    expect(Role::fromString('front_desk'))->toBe(Role::FrontDesk);
});

it('Owner is the only org-wide role', function (): void {
    expect(Role::Owner->isOrgWide())->toBeTrue();
    expect(Role::Manager->isOrgWide())->toBeFalse();
    expect(Role::FrontDesk->isOrgWide())->toBeFalse();
    expect(Role::Housekeeping->isOrgWide())->toBeFalse();
    expect(Role::Revenue->isOrgWide())->toBeFalse();
    expect(Role::Marketing->isOrgWide())->toBeFalse();
});

it('label() returns a non-empty human label for every role', function (): void {
    foreach (Role::all() as $role) {
        expect($role->label())->not->toBe('')
            ->and($role->label())->not->toContain('_');
    }
});

it('requiresTwoFactor() returns true only for Owner and Manager', function (): void {
    expect(Role::Owner->requiresTwoFactor())->toBeTrue();
    expect(Role::Manager->requiresTwoFactor())->toBeTrue();
    expect(Role::FrontDesk->requiresTwoFactor())->toBeFalse();
    expect(Role::Housekeeping->requiresTwoFactor())->toBeFalse();
    expect(Role::Revenue->requiresTwoFactor())->toBeFalse();
    expect(Role::Marketing->requiresTwoFactor())->toBeFalse();
});
