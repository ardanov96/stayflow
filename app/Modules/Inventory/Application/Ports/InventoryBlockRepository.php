<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Ports;

use App\Modules\Inventory\Contracts\DTOs\InventoryBlockDTO;

interface InventoryBlockRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): InventoryBlockDTO;

    public function find(int $blockId): ?InventoryBlockDTO;

    public function delete(int $blockId): bool;
}
