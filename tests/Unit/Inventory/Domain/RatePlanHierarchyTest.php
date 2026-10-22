<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Exceptions\CircularDerivationException;

it('formats circular derivation exception message correctly', function (): void {
    $exception = CircularDerivationException::forRatePlans(5, 3);

    expect($exception->getMessage())
        ->toContain('Rate plan 5 cannot derive from rate plan 3')
        ->toContain('circular dependency');
});
