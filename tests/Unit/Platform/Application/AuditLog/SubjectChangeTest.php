<?php

declare(strict_types=1);

use App\Modules\Platform\Application\AuditLog\SubjectChange;

it('crud() builds a CRUD-shaped change with old/new arrays', function (): void {
    $change = SubjectChange::crud(
        subjectId: 7,
        subjectType: 'App\\Modules\\Platform\\Infrastructure\\Models\\Membership',
        oldValues: ['role' => 'manager'],
        newValues: ['role' => 'owner'],
    );

    expect($change->subjectId)->toBe(7)
        ->and($change->subjectType)->toBe('App\\Modules\\Platform\\Infrastructure\\Models\\Membership')
        ->and($change->oldValues)->toBe(['role' => 'manager'])
        ->and($change->newValues)->toBe(['role' => 'owner'])
        ->and($change->metadata)->toBeNull();
});

it('crud() accepts null for oldValues or newValues (create / delete semantics)', function (): void {
    $created = SubjectChange::crud(1, 'X', null, ['x' => 1]);
    $deleted = SubjectChange::crud(1, 'X', ['x' => 1], null);

    expect($created->oldValues)->toBeNull()->and($created->newValues)->toBe(['x' => 1])
        ->and($deleted->oldValues)->toBe(['x' => 1])->and($deleted->newValues)->toBeNull();
});

it('custom() builds a custom change with a metadata bag and no old/new values', function (): void {
    $change = SubjectChange::custom(
        subjectId: 42,
        subjectType: 'App\\Modules\\Catalog\\Infrastructure\\Models\\RatePlan',
        metadata: ['reason' => 'seasonal re-pricing', 'affected_rows' => 12],
    );

    expect($change->subjectId)->toBe(42)
        ->and($change->subjectType)->toBe('App\\Modules\\Catalog\\Infrastructure\\Models\\RatePlan')
        ->and($change->oldValues)->toBeNull()
        ->and($change->newValues)->toBeNull()
        ->and($change->metadata)->toBe(['reason' => 'seasonal re-pricing', 'affected_rows' => 12]);
});

it('rejects a non-positive subjectId', function (): void {
    SubjectChange::crud(0, 'X', null, null);
})->throws(InvalidArgumentException::class, 'subjectId must be a positive integer');

it('rejects a negative subjectId', function (): void {
    SubjectChange::crud(-1, 'X', null, null);
})->throws(InvalidArgumentException::class, 'subjectId must be a positive integer');

it('rejects an empty subjectType', function (): void {
    SubjectChange::crud(1, '', null, null);
})->throws(InvalidArgumentException::class, 'subjectType must be a non-empty string');

it('rejects a subjectType over 255 characters', function (): void {
    SubjectChange::crud(1, str_repeat('A', 256), null, null);
})->throws(InvalidArgumentException::class, 'subjectType must be at most 255 characters');
