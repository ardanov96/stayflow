<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * The kind of a tax or charge applied to a money amount.
 *
 * Both kinds go through the same calculator; the distinction is preserved
 * on the breakdown for reporting (folios separate tax from service charge)
 * and for properties that only apply one kind to certain products.
 */
enum TaxKind: string
{
    case Tax = 'tax';
    case ServiceCharge = 'service_charge';
}