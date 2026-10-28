<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Controllers;

use App\Modules\Reservations\Contracts\ReservationService;
use App\Modules\Reservations\Http\Requests\CreateReservationRequest;
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
}
