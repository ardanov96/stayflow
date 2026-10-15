<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Platform\Infrastructure\Models\AuditLog;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // organization_id is filled in by the BelongsToTenant
            // `creating` observer if not set; tests that need a
            // specific org override the field explicitly.
            'organization_id' => Organization::factory(),
            'property_id' => null,
            // Default is a system event (no actor). Tests that exercise
            // the user-driven path use the `forUser` state.
            'actor_id' => null,
            'event' => 'created',
            'subject_type' => AuditLog::class,
            'subject_id' => fake()->numberBetween(1, 999_999),
            'old_values' => null,
            'new_values' => ['id' => 1],
            'metadata' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['actor_id' => $user->id]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }

    public function withEvent(string $event): static
    {
        return $this->state(fn (): array => ['event' => $event]);
    }
}
