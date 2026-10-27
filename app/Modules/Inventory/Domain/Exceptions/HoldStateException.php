<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use App\Modules\Inventory\Domain\Hold\HoldStatus;
use DomainException;

final class HoldStateException extends DomainException
{
    public static function invalidTransition(HoldStatus $from, string $action): self
    {
        return new self("Cannot {$action} an inventory hold in status '{$from->value}'.");
    }
}
