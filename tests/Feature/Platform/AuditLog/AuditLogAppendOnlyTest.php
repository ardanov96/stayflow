<?php

declare(strict_types=1);

use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\AuditLog\SubjectChange;
use App\Modules\Platform\Application\AuditLog\TenantHint;
use App\Modules\Platform\Domain\AuditLog\AuditEvent;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenantScope;
use App\Modules\Platform\Infrastructure\Models\AuditLog;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
});

it('the initial save() during create() succeeds (the only legal write path)', function (): void {
    $log = AuditLog::factory()->forOrganization($this->org)->create();

    expect($log->exists)->toBeTrue()
        ->and($log->id)->toBeGreaterThan(0);
});

it('rejects save() on an existing AuditLog row', function (): void {
    $log = AuditLog::factory()->forOrganization($this->org)->create();
    $log->event = 'tampered';

    expect(fn () => $log->save())->toThrow(
        RuntimeException::class,
        'AuditLog entries are append-only and cannot be updated.',
    );
});

it('rejects fill() + save() (the path Model::update() takes)', function (): void {
    $log = AuditLog::factory()->forOrganization($this->org)->create();

    expect(fn () => $log->update(['event' => 'tampered']))->toThrow(
        RuntimeException::class,
        'AuditLog entries are append-only and cannot be updated.',
    );
});

it('rejects delete() unconditionally', function (): void {
    $log = AuditLog::factory()->forOrganization($this->org)->create();

    expect(fn () => $log->delete())->toThrow(
        RuntimeException::class,
        'AuditLog entries are append-only and cannot be deleted.',
    );
});

it('rejects forceDelete() too (it routes through delete())', function (): void {
    $log = AuditLog::factory()->forOrganization($this->org)->create();

    // The override returns `never`, so forceDelete() cannot return
    // a result; it has to throw. We assert by checking the row is
    // still present after the attempt.
    try {
        $log->forceDelete();
    } catch (RuntimeException) {
        // expected
    }

    expect(AuditLog::withoutGlobalScope(BelongsToTenantScope::class)
        ->find($log->id))->not->toBeNull();
});

it('the Auditor port can still write new rows even when old ones exist', function (): void {
    $first = AuditLog::factory()->forOrganization($this->org)->create();
    expect($first->id)->toBeGreaterThan(0);

    // A second write through the port is allowed; only updates to
    // existing rows and deletes are blocked.
    app(Auditor::class)->record(
        AuditEvent::Created,
        SubjectChange::crud(
            subjectId: 999,
            subjectType: AuditLog::class,
            oldValues: null,
            newValues: ['note' => 'second write'],
        ),
        new TenantHint(organizationId: $this->org->id),
    );

    $count = AuditLog::withoutGlobalScope(BelongsToTenantScope::class)
        ->where('organization_id', $this->org->id)
        ->count();
    expect($count)->toBe(2);
});
