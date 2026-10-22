<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Policy\PolicyType;

it('has all policy types defined', function (): void {
    expect(PolicyType::Cancellation->value)->toBe('cancellation')
        ->and(PolicyType::Payment->value)->toBe('payment')
        ->and(PolicyType::HouseRules->value)->toBe('house_rules')
        ->and(PolicyType::CheckInCheckOut->value)->toBe('checkin_checkout');

    expect(PolicyType::all())->toHaveCount(4);
});
