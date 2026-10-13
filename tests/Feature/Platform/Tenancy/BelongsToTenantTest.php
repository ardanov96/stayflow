<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenantScope;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org1 = Organization::factory()->create();
    $this->org2 = Organization::factory()->create();
    $this->context = app(OrganizationContext::class);
});

it('returns zero rows when no context is active', function (): void {
    Property::factory()->create(['organization_id' => $this->org1->id]);

    expect(Property::all())->toHaveCount(0);
});

it('filters reads to the active organization only', function (): void {
    $p1 = Property::factory()->create(['organization_id' => $this->org1->id]);
    Property::factory()->create(['organization_id' => $this->org2->id]);

    $this->context->swap(TenantContext::forOrganization($this->org1->id), function () use ($p1): void {
        $ids = Property::all()->pluck('id')->all();
        expect($ids)->toBe([$p1->id]);
    });
});

it('also filters by property_id when the context has one (Membership)', function (): void {
    $p1 = Property::factory()->create(['organization_id' => $this->org1->id]);
    $p2 = Property::factory()->create(['organization_id' => $this->org1->id]);
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    Membership::factory()->create([
        'user_id' => $u1->id, 'organization_id' => $this->org1->id, 'property_id' => $p1->id,
    ]);
    Membership::factory()->create([
        'user_id' => $u2->id, 'organization_id' => $this->org1->id, 'property_id' => $p2->id,
    ]);

    $this->context->swap(
        TenantContext::forOrganizationAndProperty($this->org1->id, $p1->id),
        function () use ($u1): void {
            $m = Membership::all();
            expect($m->pluck('user_id')->all())->toBe([$u1->id]);
        },
    );
});

it('cannot update a row from another tenant', function (): void {
    $p = Property::factory()->create(['organization_id' => $this->org1->id]);
    $originalName = $p->name;

    $this->context->swap(TenantContext::forOrganization($this->org2->id), function () use ($p): void {
        $affected = Property::where('id', $p->id)->update(['name' => 'hacked']);
        expect($affected)->toBe(0);
    });

    $this->context->swap(TenantContext::forOrganization($this->org1->id), function () use ($p, $originalName): void {
        expect(Property::find($p->id)->name)->toBe($originalName);
    });
});

it('cannot delete a row from another tenant', function (): void {
    $p = Property::factory()->create(['organization_id' => $this->org1->id]);

    $this->context->swap(TenantContext::forOrganization($this->org2->id), function () use ($p): void {
        $affected = Property::where('id', $p->id)->delete();
        expect($affected)->toBe(0);
    });

    $this->context->swap(TenantContext::forOrganization($this->org1->id), function () use ($p): void {
        expect(Property::find($p->id))->not->toBeNull();
    });
});

it('withoutGlobalScope allows explicit cross-tenant reads', function (): void {
    $p1 = Property::factory()->create(['organization_id' => $this->org1->id]);
    $p2 = Property::factory()->create(['organization_id' => $this->org2->id]);

    $rows = Property::withoutGlobalScope(BelongsToTenantScope::class)->get();
    expect($rows->pluck('id')->sort()->values()->all())
        ->toBe(collect([$p1->id, $p2->id])->sort()->values()->all());
});

it('auto-fills organization_id from the active context on create', function (): void {
    $this->context->swap(TenantContext::forOrganization($this->org1->id), function (): void {
        $p = Property::create(['name' => 'X', 'slug' => 'x-1']);
        expect($p->organization_id)->toBe($this->org1->id);
    });
});

it('does not override an explicit organization_id', function (): void {
    $this->context->swap(TenantContext::forOrganization($this->org1->id), function (): void {
        $p = Property::create([
            'name' => 'X',
            'slug' => 'x-1',
            'organization_id' => $this->org2->id,
        ]);
        expect($p->organization_id)->toBe($this->org2->id);
    });
});

it('throws when creating a tenant row without context and without explicit org_id', function (): void {
    Property::create(['name' => 'X', 'slug' => 'x-1']);
})->throws(RuntimeException::class);
