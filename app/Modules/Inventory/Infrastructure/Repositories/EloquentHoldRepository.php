<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Repositories;

use App\Modules\Inventory\Application\Ports\HoldRepository;
use App\Modules\Inventory\Contracts\DTOs\InventoryHoldDTO;
use App\Modules\Inventory\Contracts\DTOs\InventoryHoldLineDTO;
use App\Modules\Inventory\Domain\Hold\HoldStatus;
use App\Modules\Inventory\Infrastructure\Models\InventoryHold;
use App\Modules\Inventory\Infrastructure\Models\InventoryHoldLine;
use Illuminate\Database\Eloquent\Collection;

final class EloquentHoldRepository implements HoldRepository
{
    public function find(int $organizationId, int $holdId): ?InventoryHoldDTO
    {
        /** @var InventoryHold|null $model */
        $model = InventoryHold::query()
            ->where('organization_id', $organizationId)
            ->where('id', $holdId)
            ->first();

        return $model !== null ? $this->toDTO($model) : null;
    }

    public function findForUpdate(int $organizationId, int $holdId): ?InventoryHoldDTO
    {
        /** @var InventoryHold|null $model */
        $model = InventoryHold::query()
            ->where('organization_id', $organizationId)
            ->where('id', $holdId)
            ->lockForUpdate()
            ->first();

        return $model !== null ? $this->toDTO($model) : null;
    }

    public function findByIdempotencyKey(int $organizationId, string $idempotencyKey): ?InventoryHoldDTO
    {
        /** @var InventoryHold|null $model */
        $model = InventoryHold::query()
            ->where('organization_id', $organizationId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $model !== null ? $this->toDTO($model) : null;
    }

    public function findByIdempotencyKeyForUpdate(int $organizationId, string $idempotencyKey): ?InventoryHoldDTO
    {
        /** @var InventoryHold|null $model */
        $model = InventoryHold::query()
            ->where('organization_id', $organizationId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        return $model !== null ? $this->toDTO($model) : null;
    }

    public function create(array $data): InventoryHoldDTO
    {
        /** @var InventoryHold $model */
        $model = InventoryHold::query()->create($data);

        return $this->toDTO($model);
    }

    public function createLines(int $organizationId, int $holdId, int $propertyId, array $lines): void
    {
        foreach ($lines as $line) {
            InventoryHoldLine::query()->create([
                'organization_id' => $organizationId,
                'hold_id' => $holdId,
                'property_id' => $propertyId,
                'date' => $line['date'],
                'units' => $line['units'],
            ]);
        }
    }

    public function linesFor(int $organizationId, int $holdId): array
    {
        /** @var Collection<int, InventoryHoldLine> $models */
        $models = InventoryHoldLine::query()
            ->where('organization_id', $organizationId)
            ->where('hold_id', $holdId)
            ->orderBy('date')
            ->get();

        $lines = [];
        foreach ($models as $model) {
            $lines[] = new InventoryHoldLineDTO(
                id: $model->id,
                organizationId: $model->organization_id,
                holdId: $model->hold_id,
                propertyId: $model->property_id,
                date: $this->dateString($model->getRawOriginal('date')),
                units: $model->units,
            );
        }

        return $lines;
    }

    public function transition(
        int $organizationId,
        int $holdId,
        HoldStatus $from,
        HoldStatus $to,
        array $attributes = [],
    ): bool {
        $values = array_merge($attributes, [
            'status' => $to->value,
            'updated_at' => now(),
        ]);

        $updated = InventoryHold::query()
            ->where('organization_id', $organizationId)
            ->where('id', $holdId)
            ->where('status', $from->value)
            ->update($values);

        return $updated > 0;
    }

    public function findExpired(int $organizationId, int $limit): array
    {
        /** @var Collection<int, InventoryHold> $models */
        $models = InventoryHold::query()
            ->where('organization_id', $organizationId)
            ->where('status', HoldStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $holds = [];
        foreach ($models as $model) {
            $holds[] = $this->toDTO($model);
        }

        return $holds;
    }

    private function toDTO(InventoryHold $model): InventoryHoldDTO
    {
        return new InventoryHoldDTO(
            id: $model->id,
            organizationId: $model->organization_id,
            propertyId: $model->property_id,
            roomTypeId: $model->room_type_id,
            checkIn: $this->dateString($model->getRawOriginal('check_in')),
            checkOut: $this->dateString($model->getRawOriginal('check_out')),
            units: $model->units,
            status: $model->status,
            idempotencyKey: $model->idempotency_key,
            expiresAt: $model->expires_at->toISOString(),
            reservationId: $model->reservation_id,
            committedAt: $model->committed_at?->toISOString(),
            releasedAt: $model->released_at?->toISOString(),
            expiredAt: $model->expired_at?->toISOString(),
            releaseReason: $model->release_reason,
        );
    }

    private function dateString(mixed $raw): string
    {
        return is_string($raw)
            ? substr($raw, 0, 10)
            : substr((string) $raw, 0, 10);
    }
}
