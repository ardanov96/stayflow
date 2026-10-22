<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Room\Occupancy;

it('instantiates valid occupancy and calculates max total correctly', function (): void {
    $occupancy = new Occupancy(baseOccupancy: 2, maxAdults: 3, maxChildren: 2);

    expect($occupancy->baseOccupancy)->toBe(2)
        ->and($occupancy->maxAdults)->toBe(3)
        ->and($occupancy->maxChildren)->toBe(2)
        ->and($occupancy->maxTotal())->toBe(5)
        ->and($occupancy->canAccommodate(2, 1))->toBeTrue()
        ->and($occupancy->canAccommodate(3, 2))->toBeTrue()
        ->and($occupancy->canAccommodate(4, 0))->toBeFalse()
        ->and($occupancy->canAccommodate(2, 3))->toBeFalse()
        ->and($occupancy->canAccommodate(0, 1))->toBeFalse();
});

it('rejects base occupancy less than 1', function (): void {
    expect(fn () => new Occupancy(baseOccupancy: 0, maxAdults: 2))
        ->toThrow(InvalidArgumentException::class, 'Base occupancy must be at least 1');
});

it('rejects max adults less than base occupancy', function (): void {
    expect(fn () => new Occupancy(baseOccupancy: 2, maxAdults: 1))
        ->toThrow(InvalidArgumentException::class, 'Max adults (1) cannot be less than base occupancy (2)');
});

it('rejects negative max children', function (): void {
    expect(fn () => new Occupancy(baseOccupancy: 2, maxAdults: 2, maxChildren: -1))
        ->toThrow(InvalidArgumentException::class, 'Max children cannot be negative');
});
