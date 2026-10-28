<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Infrastructure\Repositories;

use App\Modules\Reservations\Contracts\DTOs\ReservationDTO;
use App\Modules\Reservations\Contracts\ReservationRepository;
use App\Modules\Reservations\Domain\Reservation;
use App\Modules\Reservations\Infrastructure\Models\Reservation as ReservationModel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The reservations, on the database.
 */
final class EloquentReservationRepository implements ReservationRepository
{
    public function find(int $organizationId, int $reservationId): ?ReservationDTO
    {
        $model = ReservationModel::query()
            ->where('organization_id', $organizationId)
            ->find($reservationId);

        return $model === null ? null : $this->toDto($model);
    }

    public function findByCode(int $organizationId, int $propertyId, string $code): ?ReservationDTO
    {
        $model = ReservationModel::query()
            ->where('organization_id', $organizationId)
            ->where('property_id', $propertyId)
            ->where('code', $code)
            ->first();

        return $model === null ? null : $this->toDto($model);
    }

    public function findByHold(int $organizationId, int $holdId): ?ReservationDTO
    {
        $model = ReservationModel::query()
            ->where('organization_id', $organizationId)
            ->where('hold_id', $holdId)
            ->first();

        return $model === null ? null : $this->toDto($model);
    }

    public function nextCode(int $organizationId, int $propertyId, string $prefix): string
    {
        $taken = ReservationModel::query()
            ->where('organization_id', $organizationId)
            ->where('property_id', $propertyId)
            ->where('code', 'like', $prefix.'%')
            ->pluck('code')
            ->all();

        for ($sequence = 1; $sequence <= 9999; $sequence++) {
            $code = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            if (! in_array($code, $taken, true)) {
                return $code;
            }
        }

        throw new RuntimeException('No free reservation code is left for this property today.');
    }

    public function save(Reservation $reservation): void
    {
        $values = [
            'organization_id' => $reservation->organizationId,
            'property_id' => $reservation->propertyId,
            'code' => $reservation->code,
            'status' => $reservation->status()->value,
            'source' => $reservation->source->value,
            'channel_ref' => $reservation->channelRef,
            'guest_id' => $reservation->guestId,
            'arrival' => $reservation->arrival,
            'departure' => $reservation->departure,
            'adults' => $reservation->adults,
            'children' => $reservation->children,
            'total_minor' => $reservation->totalMinor,
            'currency' => $reservation->currency,
            'version' => $reservation->version(),
            'hold_id' => $reservation->holdId(),
            'updated_at' => now(),
        ];

        if ($reservation->id() === null) {
            $values['created_at'] = now();

            $id = DB::table('reservations')->insertGetId($values);

            $reservation->assignId((int) $id);

            return;
        }

        $updated = DB::table('reservations')
            ->where('id', $reservation->id())
            ->where('organization_id', $reservation->organizationId)
            ->where('version', $reservation->expectedVersion())
            ->update($values);

        if ($updated === 0) {
            throw new RuntimeException(sprintf(
                'The reservation %d was changed by somebody else in the meantime.',
                $reservation->id(),
            ));
        }
    }

    public function saveRooms(Reservation $reservation): void
    {
        DB::table('reservation_rooms')
            ->where('reservation_id', $reservation->id())
            ->delete();

        foreach ($reservation->rooms() as $room) {
            DB::table('reservation_rooms')->insert([
                'organization_id' => $reservation->organizationId,
                'reservation_id' => $reservation->id(),
                'property_id' => $reservation->propertyId,
                'room_type_id' => $room->roomTypeId,
                'rate_plan_id' => $room->ratePlanId,
                'room_unit_id' => $room->roomUnitId,
                'nights' => json_encode($room->nights),
                'total_minor' => $room->totalMinor(),
                'currency' => $reservation->currency,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function saveGuests(Reservation $reservation, int $primaryGuestId, array $companionGuestIds): void
    {
        DB::table('reservation_guests')
            ->where('reservation_id', $reservation->id())
            ->delete();

        $rows = [[
            'organization_id' => $reservation->organizationId,
            'reservation_id' => $reservation->id(),
            'guest_id' => $primaryGuestId,
            'role' => 'primary',
            'created_at' => now(),
            'updated_at' => now(),
        ]];

        foreach ($companionGuestIds as $companionGuestId) {
            $rows[] = [
                'organization_id' => $reservation->organizationId,
                'reservation_id' => $reservation->id(),
                'guest_id' => $companionGuestId,
                'role' => 'companion',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('reservation_guests')->insert($rows);
    }

    public function appendEvent(
        int $organizationId,
        int $reservationId,
        string $type,
        array $payload = [],
        ?int $actorId = null,
    ): void {
        DB::table('reservation_events')->insert([
            'organization_id' => $organizationId,
            'reservation_id' => $reservationId,
            'type' => $type,
            'payload' => $payload === [] ? null : json_encode($payload),
            'actor_id' => $actorId,
            'at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function toDto(ReservationModel $model): ReservationDTO
    {
        /** @var list<array{room_type_id: int, rate_plan_id: int|null, room_unit_id: int|null, nights: list<array{date: string, price_minor: int}>, total_minor: int}> $rooms */
        $rooms = DB::table('reservation_rooms')
            ->where('reservation_id', $model->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => [
                'room_type_id' => (int) $row->room_type_id,
                'rate_plan_id' => $row->rate_plan_id !== null ? (int) $row->rate_plan_id : null,
                'room_unit_id' => $row->room_unit_id !== null ? (int) $row->room_unit_id : null,
                'nights' => json_decode((string) $row->nights, true) ?: [],
                'total_minor' => (int) $row->total_minor,
            ])
            ->all();

        /** @var list<int> $guestIds */
        $guestIds = DB::table('reservation_guests')
            ->where('reservation_id', $model->id)
            ->orderBy('id')
            ->pluck('guest_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return new ReservationDTO(
            id: (int) $model->id,
            organizationId: (int) $model->organization_id,
            propertyId: (int) $model->property_id,
            code: (string) $model->code,
            status: (string) $model->status,
            source: (string) $model->source,
            channelRef: $model->channel_ref !== null ? (string) $model->channel_ref : null,
            guestId: (int) $model->guest_id,
            arrival: substr((string) $model->getRawOriginal('arrival'), 0, 10),
            departure: substr((string) $model->getRawOriginal('departure'), 0, 10),
            adults: (int) $model->adults,
            children: (int) $model->children,
            totalMinor: (int) $model->total_minor,
            currency: (string) $model->currency,
            version: (int) $model->version,
            holdId: $model->hold_id !== null ? (int) $model->hold_id : null,
            rooms: $rooms,
            guestIds: $guestIds,
        );
    }
}
