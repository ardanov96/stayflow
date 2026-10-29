<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Infrastructure\Services;

use App\Modules\Catalog\Contracts\DTOs\RoomUnitDTO;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Reservations\Application\Actions\AssignRoomUnitAction;
use App\Modules\Reservations\Application\Actions\CreateReservationAction;
use App\Modules\Reservations\Application\Actions\ModifyReservationAction;
use App\Modules\Reservations\Application\Actions\SuggestRoomUnitsAction;
use App\Modules\Reservations\Contracts\DTOs\ReservationDTO;
use App\Modules\Reservations\Contracts\DTOs\ReservationModificationDTO;
use App\Modules\Reservations\Contracts\ReservationRepository;
use App\Modules\Reservations\Contracts\ReservationService;
use InvalidArgumentException;

final class EloquentReservationService implements ReservationService
{
    public function __construct(
        private CreateReservationAction $createReservation,
        private ModifyReservationAction $modifyReservation,
        private AssignRoomUnitAction $assignRoomUnitAction,
        private SuggestRoomUnitsAction $suggestRoomUnitsAction,
        private ReservationRepository $reservationRepository,
        private OrganizationContext $organizationContext,
    ) {}

    public function find(int $reservationId): ?ReservationDTO
    {
        $organizationId = $this->organizationContext->current()?->organizationId;

        if ($organizationId === null) {
            throw new InvalidArgumentException(
                'A tenant context is required to read a reservation.'
            );
        }

        return $this->reservationRepository->find($organizationId, $reservationId);
    }

    public function assignRoomUnit(
        int $reservationId,
        int $roomTypeId,
        int $roomUnitId,
        ?int $userId = null,
    ): ReservationDTO {
        return $this->withOrganization(
            fn (int $organizationId): ReservationDTO => $this->assignRoomUnitAction->assign(
                organizationId: $organizationId,
                reservationId: $reservationId,
                roomTypeId: $roomTypeId,
                roomUnitId: $roomUnitId,
                actorId: $userId,
            ),
        );
    }

    public function unassignRoomUnit(
        int $reservationId,
        int $roomTypeId,
        ?int $userId = null,
    ): ReservationDTO {
        return $this->withOrganization(
            fn (int $organizationId): ReservationDTO => $this->assignRoomUnitAction->unassign(
                organizationId: $organizationId,
                reservationId: $reservationId,
                roomTypeId: $roomTypeId,
                actorId: $userId,
            ),
        );
    }

    public function moveRoomUnit(
        int $reservationId,
        int $roomTypeId,
        int $newRoomUnitId,
        ?int $userId = null,
    ): ReservationDTO {
        return $this->withOrganization(
            fn (int $organizationId): ReservationDTO => $this->assignRoomUnitAction->move(
                organizationId: $organizationId,
                reservationId: $reservationId,
                roomTypeId: $roomTypeId,
                newRoomUnitId: $newRoomUnitId,
                actorId: $userId,
            ),
        );
    }

    /**
     * @return list<RoomUnitDTO>
     */
    public function suggestRoomUnits(int $reservationId, int $roomTypeId): array
    {
        return $this->withOrganization(
            fn (int $organizationId): array => $this->suggestRoomUnitsAction->suggest(
                organizationId: $organizationId,
                reservationId: $reservationId,
                roomTypeId: $roomTypeId,
            ),
        );
    }

    /**
     * Run the callback with the organization of the current tenant.
     *
     * @param  callable(int): mixed  $callback
     */
    private function withOrganization(callable $callback): mixed
    {
        $organizationId = $this->organizationContext->current()?->organizationId;

        if ($organizationId === null) {
            throw new InvalidArgumentException(
                'A tenant context is required to work with the reservations of the organization.'
            );
        }

        return $callback($organizationId);
    }

    public function modify(
        int $reservationId,
        ?string $arrival = null,
        ?string $departure = null,
        ?int $roomTypeId = null,
        ?int $ratePlanId = null,
        ?int $adults = null,
        ?int $children = null,
        ?int $guestId = null,
        array $companionGuestIds = [],
        ?int $userId = null,
    ): ReservationModificationDTO {
        $organizationId = $this->organizationContext->current()?->organizationId;

        if ($organizationId === null) {
            throw new InvalidArgumentException(
                'A tenant context is required to modify a reservation.'
            );
        }

        return $this->modifyReservation->execute(
            organizationId: $organizationId,
            reservationId: $reservationId,
            arrival: $arrival,
            departure: $departure,
            roomTypeId: $roomTypeId,
            ratePlanId: $ratePlanId,
            adults: $adults,
            children: $children,
            guestId: $guestId,
            companionGuestIds: $companionGuestIds,
            actorId: $userId,
        );
    }

    /**
     * @param  list<int>  $companionGuestIds
     */
    public function create(
        int $propertyId,
        int $roomTypeId,
        int $ratePlanId,
        string $arrival,
        string $departure,
        int $adults,
        int $children,
        int $guestId,
        array $companionGuestIds = [],
        string $idempotencyKey = '',
        ?int $userId = null,
    ): ReservationDTO {
        $organizationId = $this->organizationContext->current()?->organizationId;

        if ($organizationId === null) {
            throw new InvalidArgumentException(
                'A tenant context is required to create a reservation.'
            );
        }

        return $this->createReservation->execute(
            organizationId: $organizationId,
            propertyId: $propertyId,
            roomTypeId: $roomTypeId,
            ratePlanId: $ratePlanId,
            arrival: $arrival,
            departure: $departure,
            adults: $adults,
            children: $children,
            guestId: $guestId,
            companionGuestIds: $companionGuestIds,
            idempotencyKey: $idempotencyKey,
            actorId: $userId,
        );
    }
}
