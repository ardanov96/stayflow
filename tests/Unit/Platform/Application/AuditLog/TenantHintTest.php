<?php

declare(strict_types=1);

use App\Modules\Platform\Application\AuditLog\TenantHint;

it('constructs with organization id only', function (): void {
    $hint = new TenantHint(organizationId: 7);

    expect($hint->organizationId)->toBe(7)
        ->and($hint->propertyId)->toBeNull();
});

it('constructs with organization id and property id', function (): void {
    $hint = new TenantHint(organizationId: 7, propertyId: 11);

    expect($hint->organizationId)->toBe(7)
        ->and($hint->propertyId)->toBe(11);
});

it('rejects a non-positive organizationId', function (): void {
    new TenantHint(organizationId: 0);
})->throws(InvalidArgumentException::class, 'organizationId must be a positive integer');

it('rejects a non-positive propertyId when explicitly set', function (): void {
    new TenantHint(organizationId: 1, propertyId: 0);
})->throws(InvalidArgumentException::class, 'propertyId must be a positive integer when set');