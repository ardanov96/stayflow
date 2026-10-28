<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Infrastructure\Services;

use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Reservations\Application\Actions\CreateReservationAction;
use App\Modules\Reservations\Contracts\DTOs\ReservationDTO;
use App\Modules\Reservations\Contracts\ReservationService;
use InvalidArgumentException;

final class EloquentReservationService implements ReservationService
{
    public function __construct(
        private CreateReservationAction $createReservation,
        private OrganizationContext $organizationContext,
    ) {}

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
