<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Property\PropertyStatus;

it('has all required property statuses', function (): void {
    expect(PropertyStatus::Active->value)->toBe('active')
        ->and(PropertyStatus::Inactive->value)->toBe('inactive')
        ->and(PropertyStatus::Onboarding->value)->toBe('onboarding');

    expect(PropertyStatus::all())->toHaveCount(3);
});
