<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

use App\Modules\Inventory\Contracts\DTOs\RatePlanDTO;

interface RatePlanRepository
{
    public function findById(int $id): ?RatePlanDTO;

    public function findByCode(int $propertyId, string $code): ?RatePlanDTO;

    /**
     * @return list<RatePlanDTO>
     */
    public function listByProperty(int $propertyId): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): RatePlanDTO;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): RatePlanDTO;

    public function delete(int $id): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function getCancellationPolicy(int $policyId): ?array;
}
