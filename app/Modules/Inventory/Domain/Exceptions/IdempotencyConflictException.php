<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

use DomainException;

final class IdempotencyConflictException extends DomainException
{
    public static function forKey(string $idempotencyKey): self
    {
        return new self("Idempotency key '{$idempotencyKey}' was already used with a different hold payload.");
    }
}
