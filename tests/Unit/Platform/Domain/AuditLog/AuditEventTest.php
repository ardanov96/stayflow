<?php

declare(strict_types=1);

use App\Modules\Platform\Domain\AuditLog\AuditEvent;

it('has the four CRUD-shaped cases', function (): void {
    expect(AuditEvent::cases())
        ->toHaveCount(4)
        ->and(AuditEvent::Created->value)->toBe('created')
        ->and(AuditEvent::Updated->value)->toBe('updated')
        ->and(AuditEvent::Deleted->value)->toBe('deleted')
        ->and(AuditEvent::Restored->value)->toBe('restored');
});

it('fromString() accepts the four known values', function (string $value, AuditEvent $expected): void {
    expect(AuditEvent::fromString($value))->toBe($expected);
})->with([
    'created'  => ['created', AuditEvent::Created],
    'updated'  => ['updated', AuditEvent::Updated],
    'deleted'  => ['deleted', AuditEvent::Deleted],
    'restored' => ['restored', AuditEvent::Restored],
]);

it('fromString() throws on unknown value with a clear error', function (): void {
    AuditEvent::fromString('member.role.changed');
})->throws(InvalidArgumentException::class, 'Unknown audit event "member.role.changed"');