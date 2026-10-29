<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Controllers;

use App\Modules\Reservations\Contracts\ReservationService;
use App\Modules\Reservations\Http\Requests\AssignRoomUnitRequest;
use App\Modules\Reservations\Http\Requests\CreateReservationRequest;
use App\Modules\Reservations\Http\Requests\ModifyReservationRequest;
use App\Modules\Reservations\Http\Requests\MoveRoomUnitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The reservations of the PMS.
 */
final class ReservationController
{
    public function __construct(
        private readonly ReservationService $reservations,
    ) {}

    /**
     * Create a reservation.
     */
    public function store(CreateReservationRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $reservation = $this->reservations->create(
            propertyId: (int) $validated['property_id'],
            roomTypeId: (int) $validated['room_type_id'],
            ratePlanId: (int) $validated['rate_plan_id'],
            arrival: (string) $validated['arrival'],
            departure: (string) $validated['departure'],
            adults: (int) $validated['adults'],
            children: (int) $validated['children'],
            guestId: (int) $validated['guest_id'],
            companionGuestIds: array_map('intval', (array) ($validated['companion_guest_ids'] ?? [])),
            idempotencyKey: (string) $validated['idempotency_key'],
            userId: Auth::id() !== null ? (int) Auth::id() : null,
        );

        return response()->json($reservation->toArray(), 201);
    }

    /**
     * Modify a reservation.
     */
    public function update(ModifyReservationRequest $request, int $id): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $modification = $this->reservations->modify(
            reservationId: $id,
            arrival: isset($validated['arrival']) ? (string) $validated['arrival'] : null,
            departure: isset($validated['departure']) ? (string) $validated['departure'] : null,
            roomTypeId: isset($validated['room_type_id']) ? (int) $validated['room_type_id'] : null,
            ratePlanId: isset($validated['rate_plan_id']) ? (int) $validated['rate_plan_id'] : null,
            adults: isset($validated['adults']) ? (int) $validated['adults'] : null,
            children: isset($validated['children']) ? (int) $validated['children'] : null,
            guestId: isset($validated['guest_id']) ? (int) $validated['guest_id'] : null,
            companionGuestIds: array_map('intval', (array) ($validated['companion_guest_ids'] ?? [])),
            userId: Auth::id() !== null ? (int) Auth::id() : null,
        );

        return response()->json($modification->toArray());
    }

    /**
     * Assign a physical room to one of the rooms of the reservation.
     */
    public function assignRoomUnit(AssignRoomUnitRequest $request, int $id): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $reservation = $this->reservations->assignRoomUnit(
            reservationId: $id,
            roomTypeId: (int) $validated['room_type_id'],
            roomUnitId: (int) $validated['room_unit_id'],
            userId: Auth::id() !== null ? (int) Auth::id() : null,
        );

        return response()->json($reservation->toArray());
    }

    /**
     * Move the reservation to another physical room of the same type.
     */
    public function moveRoomUnit(MoveRoomUnitRequest $request, int $id): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $reservation = $this->reservations->moveRoomUnit(
            reservationId: $id,
            roomTypeId: (int) $validated['from_room_type_id'],
            newRoomUnitId: (int) $validated['room_unit_id'],
            userId: Auth::id() !== null ? (int) Auth::id() : null,
        );

        return response()->json($reservation->toArray());
    }

    /**
     * Take the physical room away.
     */
    public function unassignRoomUnit(int $id, int $roomTypeId): JsonResponse
    {
        $reservation = $this->reservations->unassignRoomUnit(
            reservationId: $id,
            roomTypeId: $roomTypeId,
            userId: Auth::id() !== null ? (int) Auth::id() : null,
        );

        return response()->json($reservation->toArray());
    }

    /**
     * The physical rooms that are free for the nights of the reservation.
     */
    public function suggestRoomUnits(Request $request, int $id): JsonResponse
    {
        /** @var array{room_type_id: int} $validated */
        $validated = $request->validate([
            'room_type_id' => ['required', 'integer', 'min:1'],
        ]);

        $units = $this->reservations->suggestRoomUnits(
            reservationId: $id,
            roomTypeId: (int) $validated['room_type_id'],
        );

        return response()->json([
            'data' => array_map(
                static fn (object $unit): array => [
                    'id' => $unit->id,
                    'number' => $unit->number,
                    'floor' => $unit->floor,
                    'status' => $unit->status,
                ],
                $units,
            ),
        ]);
    }
}
