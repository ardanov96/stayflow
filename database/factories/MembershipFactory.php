<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Modules\Platform\Infrastructure\Models\Membership;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            // null = organization-wide (per DOMAIN_MODEL). Tests that
            // need a property-scoped membership override this field.
            'property_id' => null,
            // P1-03 (RBAC) turns this into a typed enum. For now it
            // is a free string so factories and tests can pick any
            // value.
            'role' => 'manager',
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }
}
