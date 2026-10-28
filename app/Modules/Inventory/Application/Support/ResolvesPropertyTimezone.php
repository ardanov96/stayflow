<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Support;

use App\Modules\Catalog\Contracts\CatalogService;

/**
 * Resolves the timezone of a property.
 *
 * The timezone of a property decides where its day starts, so every
 * date arithmetic of the inventory has to be done in it and not in the
 * timezone of the server. The property is the source of truth; the
 * timezone of the application is only the fallback for a property that
 * does not exist.
 */
trait ResolvesPropertyTimezone
{
    private function propertyTimezone(CatalogService $catalogService, int $propertyId): string
    {
        $property = $catalogService->getProperty($propertyId);

        if ($property === null) {
            return date_default_timezone_get();
        }

        return $property->timezone;
    }
}
