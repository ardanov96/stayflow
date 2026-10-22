<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Domain\Policy\PolicyType;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Catalog\Infrastructure\Models\PropertyPolicy;
use App\Modules\Inventory\Application\Actions\UpdateRatePlanAction;
use App\Modules\Inventory\Domain\Exceptions\CircularDerivationException;
use App\Modules\Inventory\Domain\RatePlan\DerivationType;
use App\Modules\Inventory\Domain\RatePlan\MealPlan;
use App\Modules\Inventory\Infrastructure\Models\RatePlan;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();

    Membership::factory()->create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'property_id' => null,
        'role' => Role::Owner,
    ]);

    $this->context = app(OrganizationContext::class);
    $this->property = Property::factory()->create(['organization_id' => $this->org->id]);

    $this->policy = PropertyPolicy::factory()->create([
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'type' => PolicyType::Cancellation,
        'title' => 'Standard 48-Hour Cancellation',
    ]);
});

it('creates an independent rate plan and records audit log', function (): void {
    $response = $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->postJson('/api/inventory/rate-plans', [
            'property_id' => $this->property->id,
            'code' => 'BAR',
            'name' => 'Best Available Rate',
            'description' => ['en' => 'Flexible rate with breakfast', 'id' => 'Tarif fleksibel termasuk sarapan'],
            'meal_plan' => MealPlan::Breakfast->value,
            'cancellation_policy_id' => $this->policy->id,
        ]);

    $response->assertCreated();
    $data = $response->json('data');

    expect($data['code'])->toBe('BAR')
        ->and($data['name'])->toBe('Best Available Rate')
        ->and($data['meal_plan'])->toBe('breakfast')
        ->and($data['cancellation_policy_id'])->toBe($this->policy->id)
        ->and($data['parent_rate_plan_id'])->toBeNull();

    $this->assertDatabaseHas('rate_plans', [
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'code' => 'BAR',
        'cancellation_policy_id' => $this->policy->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'organization_id' => $this->org->id,
        'subject_type' => 'RatePlan',
        'event' => 'rate.changed',
    ]);
});

it('creates a derived rate plan linked to parent plan', function (): void {
    $parent = RatePlan::factory()->create([
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'code' => 'BAR',
        'name' => 'Best Available Rate',
    ]);

    $response = $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->postJson('/api/inventory/rate-plans', [
            'property_id' => $this->property->id,
            'code' => 'NR',
            'name' => 'Non-Refundable',
            'meal_plan' => MealPlan::RoomOnly->value,
            'parent_rate_plan_id' => $parent->id,
            'derivation_type' => DerivationType::Percentage->value,
            'derivation_value' => -1000, // -10.00%
        ]);

    $response->assertCreated();
    $data = $response->json('data');

    expect($data['code'])->toBe('NR')
        ->and($data['parent_rate_plan_id'])->toBe($parent->id)
        ->and($data['derivation_type'])->toBe('percentage')
        ->and($data['derivation_value'])->toBe(-1000);
});

it('prevents circular derivation on update', function (): void {
    $planA = RatePlan::factory()->create([
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'code' => 'PLAN_A',
    ]);

    $planB = RatePlan::factory()->create([
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'code' => 'PLAN_B',
        'parent_rate_plan_id' => $planA->id,
        'derivation_type' => DerivationType::Percentage,
        'derivation_value' => -500,
    ]);

    // Attempting to set Plan A's parent to Plan B creates circular dependency (A -> B -> A)
    $action = app(UpdateRatePlanAction::class);

    $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        function () use ($action, $planA, $planB): void {
            expect(fn () => $action->execute(
                ratePlanId: $planA->id,
                data: ['parent_rate_plan_id' => $planB->id],
                actorId: $this->user->id,
            ))->toThrow(CircularDerivationException::class);
        }
    );
});
