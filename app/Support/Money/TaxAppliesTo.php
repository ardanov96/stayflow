<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * What a {@see \App\Support\Money\TaxRule} applies to.
 *
 * `All` is the default for most properties. Room and AddOn are used by the
 * booking engine to compute a quote total when an add-on (e.g. airport
 * transfer) has a different tax profile than the room rate.
 */
enum TaxAppliesTo: string
{
    case Room = 'room';
    case AddOn = 'addon';
    case All = 'all';
}