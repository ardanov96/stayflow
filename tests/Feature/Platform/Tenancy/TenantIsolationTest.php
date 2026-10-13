<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Cache\TenantCache;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenantScope;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The end-to-end isolation suite promised by BACKLOG P1-01:
 * "proves cross-tenant read/update/delete is impossible via
 * web, API, and jobs." Composed of multiple test groups because
 * the assertions share fixtures but exercise different surfaces
 * (Eloquent, jobs, cache, HTTP).
 *
 * The Property and Membership models are tenant-scoped (BelongsToTenant).
 * The scope is the only thing that should ever make org A and org B
 * rows visible to the right caller. Any test that produces the wrong
 * rows (or fails to reject the wrong rows) is a security regression.
 */

beforeEach(function (): void {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->context = app(OrganizationContext::class);
    $this->cache = app(TenantCache::class);
});

// --- Eloquent surface ------------------------------------------------------

it('reads are scoped: org A cannot see org B properties', function (): void {
    $aProps = Property::factory()->count(2)->create(['organization_id' => $this->orgA->id]);
    Property::factory()->count(3)->create(['organization_id' => $this->orgB->id]);

    $this->context->swap(TenantContext::forOrganization($this->orgA->id), function () use ($aProps): void {
        $ids = Property::all()->pluck('id')->sort()->values()->all();
        expect($ids)->toBe($aProps->pluck('id')->sort()->values()->all());
    });
});

it('updates are scoped: org A cannot update an org B row', function (): void {
    $p = Property::factory()->create(['organization_id' => $this->orgA->id]);
    $originalName = $p->name;

    $this->context->swap(TenantContext::forOrganization($this->orgB->id), function () use ($p): void {
        $affected = Property::where('id', $p->id)->update(['name' => 'hacked']);
        expect($affected)->toBe(0);
    });

    $this->context->swap(TenantContext::forOrganization($this->orgA->id), function () use ($p, $originalName): void {
        expect(Property::find($p->id)->name)->toBe($originalName);
    });
});

it('deletes are scoped: org A cannot delete an org B row', function (): void {
    $p = Property::factory()->create(['organization_id' => $this->orgA->id]);

    $this->context->swap(TenantContext::forOrganization($this->orgB->id), function () use ($p): void {
        expect(Property::where('id', $p->id)->delete())->toBe(0);
    });

    $this->context->swap(TenantContext::forOrganization($this->orgA->id), function () use ($p): void {
        expect(Property::find($p->id))->not->toBeNull();
    });
});

it('membership reads are also property-scoped when the context has a propertyId', function (): void {
    $pA = Property::factory()->create(['organization_id' => $this->orgA->id]);
    $pB = Property::factory()->create(['organization_id' => $this->orgA->id]);
    $uA = User::factory()->create();
    $uB = User::factory()->create();
    $mA = Membership::factory()->create([
        'user_id' => $uA->id,
        'organization_id' => $this->orgA->id,
        'property_id' => $pA->id,
    ]);
    Membership::factory()->create([
        'user_id' => $uB->id,
        'organization_id' => $this->orgA->id,
        'property_id' => $pB->id,
    ]);

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->orgA->id, $pA->id),
        function () use ($mA): void {
            $ids = Membership::all()->pluck('id')->all();
            expect($ids)->toBe([$mA->id]);
        },
    );
});

it('withoutGlobalScope is the only escape hatch and must be opted into', function (): void {
    $a = Property::factory()->create(['organization_id' => $this->orgA->id]);
    $b = Property::factory()->create(['organization_id' => $this->orgB->id]);

    expect(Property::all())->toHaveCount(0);

    $rows = Property::withoutGlobalScope(BelongsToTenantScope::class)->get();
    expect($rows->pluck('id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

// --- Cache surface --------------------------------------------------------

it('TenantCache namespaces by organization: org A cannot see org B keys', function (): void {
    $this->context->swap(TenantContext::forOrganization($this->orgA->id), function (): void {
        $this->cache->put('rates.today', 'A-value', 60);
    });
    $this->context->swap(TenantContext::forOrganization($this->orgB->id), function (): void {
        $this->cache->put('rates.today', 'B-value', 60);
    });

    $this->context->swap(TenantContext::forOrganization($this->orgA->id), function (): void {
        expect($this->cache->get('rates.today'))->toBe('A-value');
    });
    $this->context->swap(TenantContext::forOrganization($this->orgB->id), function (): void {
        expect($this->cache->get('rates.today'))->toBe('B-value');
    });
});

// --- Job surface ----------------------------------------------------------

it('a job dispatched under org A context runs with org A context', function (): void {
    $sentinel = new stdClass();
    $sentinel->observed = null;

    $job = new \Tests\Support\TenantContextRecordingJob(TenantContext::forOrganization(123));
    dispatch($job);

    expect(\Tests\Support\TenantContextRecordingJob::$lastObserved)->toBe(['org_id' => 123, 'property_id' => null]);
});

// --- HTTP surface ---------------------------------------------------------

it('the test /_testing/tenant/properties route is scoped to the X-Organization-Id header', function (): void {
    Property::factory()->count(2)->create(['organization_id' => $this->orgA->id]);
    Property::factory()->count(3)->create(['organization_id' => $this->orgB->id]);

    $response = $this->getJson('/_testing/tenant/properties', [
        'X-Organization-Id' => (string) $this->orgA->id,
    ]);
    $response->assertOk();
    expect($response->json('properties'))->toHaveCount(2);
});

it('the test /_testing/tenant/memberships route is scoped to the X-Organization-Id header', function (): void {
    $usersA = User::factory()->count(1)->create();
    $usersA->each(fn ($u) => Membership::factory()->create([
        'user_id' => $u->id,
        'organization_id' => $this->orgA->id,
    ]));
    $usersB = User::factory()->count(4)->create();
    $usersB->each(fn ($u) => Membership::factory()->create([
        'user_id' => $u->id,
        'organization_id' => $this->orgB->id,
    ]));

    $response = $this->getJson('/_testing/tenant/memberships', [
        'X-Organization-Id' => (string) $this->orgA->id,
    ]);
    $response->assertOk();
    expect($response->json('memberships'))->toHaveCount(1);
});

it('the test /_testing/tenant/ping echoes the active context', function (): void {
    $response = $this->getJson('/_testing/tenant/ping', [
        'X-Organization-Id' => (string) $this->orgA->id,
        'X-Property-Id' => '99',
    ]);
    $response->assertOk();
    expect($response->json('organization_id'))->toBe($this->orgA->id)
        ->and($response->json('property_id'))->toBe(99);
});

// --- Raw SQL surface ------------------------------------------------------

it('raw SQL queries do not get the scope (the developer must add the tenant condition explicitly)', function (): void {
    $pA = Property::factory()->create(['organization_id' => $this->orgA->id]);
    Property::factory()->create(['organization_id' => $this->orgB->id]);

    $this->context->swap(TenantContext::forOrganization($this->orgA->id), function () use ($pA): void {
        $unscoped = DB::table('properties')->pluck('id')->all();
        expect($unscoped)->toContain($pA->id);

        $scoped = DB::table('properties')
            ->where('organization_id', app(OrganizationContext::class)->current()->organizationId)
            ->pluck('id')
            ->all();
        expect($scoped)->toBe([$pA->id]);
    });
});