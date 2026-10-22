<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Application\Actions\CreateTaxRuleAction;
use App\Modules\Catalog\Application\Actions\SetPropertyPolicyAction;
use App\Modules\Catalog\Domain\Policy\PolicyType;
use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Identity\Role;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use App\Support\Money\Money;
use App\Support\Money\TaxAppliesTo;
use App\Support\Money\TaxCalculator;
use App\Support\Money\TaxKind;
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
});

it('creates tax rules and bridges correctly to P1-05 TaxCalculator', function (): void {
    $action = app(CreateTaxRuleAction::class);

    // Indonesian hospitality standard: 10% Service Charge + 10% PB1
    [$serviceCharge, $pb1Tax] = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        function () use ($action) {
            $sc = $action->execute(
                property: $this->property,
                data: [
                    'name' => 'Service Charge',
                    'kind' => TaxKind::ServiceCharge,
                    'rate_bps' => 1000, // 10%
                    'is_inclusive' => false,
                    'applies_to' => TaxAppliesTo::Room,
                    'order' => 1,
                ],
                actorId: $this->user->id,
            );

            $pb1 = $action->execute(
                property: $this->property,
                data: [
                    'name' => 'PB1 Pajak Hotel',
                    'kind' => TaxKind::Tax,
                    'rate_bps' => 1000, // 10%
                    'is_inclusive' => false,
                    'applies_to' => TaxAppliesTo::Room,
                    'order' => 2,
                ],
                actorId: $this->user->id,
            );

            return [$sc, $pb1];
        },
    );

    expect($serviceCharge->rate_bps)->toBe(1000)
        ->and($pb1Tax->rate_bps)->toBe(1000);

    // Calculate tax on 1,000,000 IDR room charge using P1-05 TaxCalculator
    $rules = [
        $serviceCharge->toDomainRule(),
        $pb1Tax->toDomainRule(),
    ];

    $subtotal = Money::ofMinor(1_000_000, 'IDR');
    $breakdown = (new TaxCalculator)->apply($subtotal, ...$rules);

    // 1,000,000 + 100,000 SC + 110,000 PB1 (tax on room + SC) = 1,210,000 IDR
    expect($breakdown->subtotal->amountMinor)->toBe(1_000_000)
        ->and($breakdown->total->amountMinor)->toBe(1_210_000)
        ->and($breakdown->lines)->toHaveCount(2);
});

it('sets and updates property policy via action and HTTP', function (): void {
    $action = app(SetPropertyPolicyAction::class);

    $policy = $this->context->swap(
        TenantContext::forOrganization($this->org->id),
        fn () => $action->execute(
            property: $this->property,
            data: [
                'type' => PolicyType::Cancellation,
                'title' => 'Standard Policy',
                'description' => ['en' => 'Free cancellation 48h before check-in.'],
                'structured_terms' => ['free_cancellation_hours' => 48],
            ],
            actorId: $this->user->id,
        ),
    );

    expect($policy->title)->toBe('Standard Policy')
        ->and($policy->type)->toBe(PolicyType::Cancellation);

    // Update via HTTP
    $response = $this->actingAs($this->user)
        ->withHeader('X-Organization-Id', (string) $this->org->id)
        ->postJson("/api/catalog/properties/{$this->property->id}/policies", [
            'type' => 'cancellation',
            'title' => 'Strict Cancellation Policy',
            'description' => ['en' => 'Non-refundable.'],
            'structured_terms' => ['free_cancellation_hours' => 0],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Strict Cancellation Policy');

    expect($this->property->policies()->withoutGlobalScopes()->count())->toBe(1);
});
