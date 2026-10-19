<?php

declare(strict_types=1);

use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenantScope;
use App\Modules\Platform\Infrastructure\Models\AuditLog;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->context = app(OrganizationContext::class);
});

it('the AuditLog model uses the BelongsToTenant trait', function (): void {
    $traits = class_uses_recursive(AuditLog::class);
    expect($traits)->toContain(BelongsToTenant::class);
});

it('returns zero rows when no context is active (scope short-circuits to 1 = 0)', function (): void {
    AuditLog::factory()->forOrganization($this->orgA)->create();
    AuditLog::factory()->forOrganization($this->orgB)->create();

    expect(AuditLog::all())->toHaveCount(0);
});

it('filters reads to the active organization only', function (): void {
    $a = AuditLog::factory()->forOrganization($this->orgA)->create();
    $b = AuditLog::factory()->forOrganization($this->orgB)->create();

    $this->context->swap(
        TenantContext::forOrganization($this->orgA->id),
        function () use ($a): void {
            $ids = AuditLog::all()->pluck('id')->all();
            expect($ids)->toBe([$a->id]);
        },
    );
});

it('withoutGlobalScope allows explicit cross-tenant reads (for the admin viewer)', function (): void {
    AuditLog::factory()->forOrganization($this->orgA)->create();
    AuditLog::factory()->forOrganization($this->orgB)->create();

    expect(AuditLog::withoutGlobalScope(BelongsToTenantScope::class)->count())->toBe(2);
});

it('auto-fills organization_id from the subject model on create', function (): void {
    $this->context->swap(
        TenantContext::forOrganization($this->orgA->id),
        function (): void {
            $log = AuditLog::factory()->create(['organization_id' => $this->orgA->id]);
            expect($log->organization_id)->toBe($this->orgA->id);
        },
    );
});
