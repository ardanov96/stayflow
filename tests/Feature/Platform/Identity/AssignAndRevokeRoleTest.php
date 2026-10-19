<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Application\AuditLog\Ports\Auditor;
use App\Modules\Platform\Application\Identity\AssignRole;
use App\Modules\Platform\Application\Identity\RevokeRole;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Infrastructure\Models\AuditLog;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->auditor = app(Auditor::class);
    $this->assignAction = new AssignRole($this->auditor);
    $this->revokeAction = new RevokeRole($this->auditor);

    $this->org = Organization::factory()->create();
    $this->actor = User::factory()->create();
    $this->subject = User::factory()->create();
});

it('assigns a new role and records member.role.assigned in audit log', function (): void {
    $membership = $this->assignAction->execute(
        $this->actor,
        $this->subject,
        $this->org,
        null,
        Role::Owner,
    );

    expect($membership)->toBeInstanceOf(Membership::class)
        ->and($membership->user_id)->toBe($this->subject->id)
        ->and($membership->organization_id)->toBe($this->org->id)
        ->and($membership->property_id)->toBeNull()
        ->and($membership->roleEnum())->toBe(Role::Owner);

    // Audit log verification
    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'member.role.assigned')
        ->first();

    expect($log)->not->toBeNull()
        ->and((int) $log->organization_id)->toBe($this->org->id)
        ->and($log->new_values['role'])->toBe('owner')
        ->and($log->metadata['actor_id'])->toBe($this->actor->id)
        ->and($log->metadata['subject_user_id'])->toBe($this->subject->id);
});

it('updates an existing role and records member.role.changed in audit log', function (): void {
    $prop = Property::factory()->create(['organization_id' => $this->org->id]);

    // Initial assignment
    $this->assignAction->execute(
        $this->actor,
        $this->subject,
        $this->org,
        $prop->id,
        Role::FrontDesk,
    );

    // Update assignment
    $updated = $this->assignAction->execute(
        $this->actor,
        $this->subject,
        $this->org,
        $prop->id,
        Role::Manager,
    );

    expect($updated->roleEnum())->toBe(Role::Manager)
        ->and(Membership::withoutGlobalScopes()->where('user_id', $this->subject->id)->count())->toBe(1);

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'member.role.changed')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values['role'])->toBe('front_desk')
        ->and($log->new_values['role'])->toBe('manager');
});

it('rejects non-positive propertyId in AssignRole', function (): void {
    $this->assignAction->execute(
        $this->actor,
        $this->subject,
        $this->org,
        0,
        Role::FrontDesk,
    );
})->throws(InvalidArgumentException::class, 'propertyId must be a positive integer when set.');

it('revokes an existing role and records member.role.revoked in audit log', function (): void {
    $membership = $this->assignAction->execute(
        $this->actor,
        $this->subject,
        $this->org,
        null,
        Role::Housekeeping,
    );

    $revoked = $this->revokeAction->execute(
        $this->actor,
        $this->subject,
        $this->org,
        null,
    );

    expect($revoked)->toBeTrue()
        ->and(Membership::withoutGlobalScopes()->find($membership->id)?->trashed())->toBeTrue();

    $log = AuditLog::withoutGlobalScopes()
        ->where('event', 'member.role.revoked')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values['role'])->toBe('housekeeping');
});

it('returns false idempotently when revoking non-existent membership and writes no audit log', function (): void {
    $initialLogCount = AuditLog::withoutGlobalScopes()->count();

    $revoked = $this->revokeAction->execute(
        $this->actor,
        $this->subject,
        $this->org,
        999,
    );

    expect($revoked)->toBeFalse()
        ->and(AuditLog::withoutGlobalScopes()->count())->toBe($initialLogCount);
});
