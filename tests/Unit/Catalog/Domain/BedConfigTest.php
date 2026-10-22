<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Room\BedConfig;

it('matches the 5 canonical bed configs from DOMAIN_MODEL and OQ-01', function (): void {
    expect(BedConfig::King->value)->toBe('king')
        ->and(BedConfig::Twin->value)->toBe('twin')
        ->and(BedConfig::KingTwin->value)->toBe('king_twin')
        ->and(BedConfig::Double->value)->toBe('double')
        ->and(BedConfig::Single->value)->toBe('single');

    expect(BedConfig::all())->toHaveCount(5);
});
