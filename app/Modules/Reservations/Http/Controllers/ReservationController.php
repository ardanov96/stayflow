<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Controllers;

use App\Modules\Reservations\Contracts\ReservationService;
use App\Modules\Reservations\Http\Requests\CreateReservationRequest;
use App\Modules\Reservations\Http\Requests\ModifyReservationRequest;
use Illuminate\Http\JsonResponse;
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
}
