<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Infrastructure\Models\Organization;
use App\Modules\Reservations\Domain\ReservationStatus;
use App\Modules\Reservations\Infrastructure\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => function (array $attributes): int {
                if (isset($attributes['property_id'])) {
                    $property = Property::find($attributes['property_id']);

                    if ($property !== null) {
                        return (int) $property->organization_id;
                    }
                }

                return (int) Organization::factory()->create()->id;
            },
            'property_id' => Property::factory(),
            'code' => 'RSV-'.Str::upper(Str::random(8)),
            'status' => ReservationStatus::PendingPayment->value,
            'source' => 'pms',
            'channel_ref' => null,
            'guest_id' => 1,
            'arrival' => now()->addDay()->format('Y-m-d'),
            'departure' => now()->addDays(3)->format('Y-m-d'),
            'adults' => 2,
            'children' => 0,
            'total_minor' => 1000000,
            'currency' => 'IDR',
            'version' => 1,
            'hold_id' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => ['status' => ReservationStatus::Confirmed->value]);
    }
}
