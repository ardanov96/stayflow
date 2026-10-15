<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenantScope;
use App\Modules\Platform\Infrastructure\Models\AuditLog;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->context = app(OrganizationContext::class);
    $this->user = User::factory()->create();
});

it('writes a created audit log on Membership::create', function (): void {
    $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        function (): void {
            $m = Membership::factory()->create([
                'user_id' => $this->user->id,
                'organization_id' => $this->org->id,
            ]);

            $log = AuditLog::withoutGlobalScope(BelongsToTenantScope::class)
                ->where('subject_type', Membership::class)
                ->where('subject_id', $m->id)
                ->where('event', 'created')
                ->first();

            expect($log)->not->toBeNull()
                ->and($log->organization_id)->toBe($this->org->id)
                ->and($log->property_id)->toBeNull()
                ->and($log->actor_id)->toBeNull()
                ->and($log->new_values)->toBeArray()
                ->and($log->new_values['role'])->toBe('manager')
                ->and($log->old_values)->toBeNull();
        },
    );
});

it('writes an updated audit log with old and new values', function (): void {
    $membership = null;
    $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        function () use (&$membership): void {
            $membership = Membership::factory()->create([
                'user_id' => $this->user->id,
                'organization_id' => $this->org->id,
                'role' => 'manager',
            ]);
            $membership->role = 'owner';
            $membership->save();
        },
    );

    $log = AuditLog::withoutGlobalScope(BelongsToTenantScope::class)
        ->where('subject_type', Membership::class)
        ->where('subject_id', $membership->id)
        ->where('event', 'updated')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values['role'])->toBe('manager')
        ->and($log->new_values['role'])->toBe('owner');
});

it('writes a deleted audit log on Membership::delete', function (): void {
    $membership = null;
    $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        function () use (&$membership): void {
            $membership = Membership::factory()->create([
                'user_id' => $this->user->id,
                'organization_id' => $this->org->id,
            ]);
            $membership->delete();
        },
    );

    $log = AuditLog::withoutGlobalScope(BelongsToTenantScope::class)
        ->where('subject_type', Membership::class)
        ->where('subject_id', $membership->id)
        ->where('event', 'deleted')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values['role'])->toBe('manager')
        ->and($log->new_values)->toBeNull();
});

it('captures the actor from Auth::id() when a user is logged in', function (): void {
    $this->actingAs($this->user);
    $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        function (): void {
            $m = Membership::factory()->create([
                'user_id' => $this->user->id,
                'organization_id' => $this->org->id,
            ]);

            $log = AuditLog::withoutGlobalScope(BelongsToTenantScope::class)
                ->where('subject_type', Membership::class)
                ->where('subject_id', $m->id)
                ->where('event', 'created')
                ->first();

            expect($log->actor_id)->toBe($this->user->id);
        },
    );
});

it('captures property_id from the model when the subject is property-scoped', function (): void {
    $property = \App\Modules\Catalog\Infrastructure\Models\Property::factory()
        ->create(['organization_id' => $this->org->id]);

    $membership = null;
    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org->id, $property->id),
        function () use (&$membership, $property): void {
            $membership = Membership::factory()->create([
                'user_id' => $this->user->id,
                'organization_id' => $this->org->id,
                'property_id' => $property->id,
            ]);
        },
    );

    $log = AuditLog::withoutGlobalScope(BelongsToTenantScope::class)
        ->where('subject_type', Membership::class)
        ->where('subject_id', $membership->id)
        ->where('event', 'created')
        ->first();

    expect($log->property_id)->toBe($property->id);
});

it('respects auditExclude() to redact sensitive attributes', function (): void {
    // Build a one-off model that opts into Auditable and excludes
    // its sensitive attribute. We do not need a database migration
    // for this: the model is throwaway and the test is about the
    // filter, not the schema.
    $model = new class extends \Illuminate\Database\Eloquent\Model {
        use \App\Modules\Platform\Infrastructure\Concerns\Auditable;

        protected $table = 'memberships';
        protected $guarded = [];
        public $timestamps = false;

        protected function auditExclude(): array
        {
            return ['role'];
        }
    };

    $membership = $model::create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'role' => 'manager',
    ]);

    $log = AuditLog::withoutGlobalScope(BelongsToTenantScope::class)
        ->where('subject_type', $model::class)
        ->where('subject_id', $membership->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values)->not->toHaveKey('role')
        ->and($log->new_values)->toHaveKey('user_id')
        ->and($log->new_values)->toHaveKey('organization_id');
});

it('auditExclude() returning ["*"] redacts everything', function (): void {
    $model = new class extends \Illuminate\Database\Eloquent\Model {
        use \App\Modules\Platform\Infrastructure\Concerns\Auditable;

        protected $table = 'memberships';
        protected $guarded = [];
        public $timestamps = false;

        protected function auditExclude(): array
        {
            return ['*'];
        }
    };

    $membership = $model::create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'role' => 'manager',
    ]);

    $log = AuditLog::withoutGlobalScope(BelongsToTenantScope::class)
        ->where('subject_type', $model::class)
        ->where('subject_id', $membership->id)
        ->where('event', 'created')
        ->first();

    expect($log->new_values)->toBe([]);
});