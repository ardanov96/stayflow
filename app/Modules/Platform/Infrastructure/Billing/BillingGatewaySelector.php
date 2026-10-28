<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Billing;

use App\Modules\Platform\Contracts\BillingGateway;
use InvalidArgumentException;

/**
 * The gateway of an organization.
 *
 * The organization chooses its gateway (ADR-0013): the primary one
 * supports the payment methods of OQ-05, the secondary one the cards.
 * Everything else in the application is the same for both.
 */
final class BillingGatewaySelector
{
    /**
     * @param  array<string, BillingGateway>  $gateways
     */
    public function __construct(private readonly array $gateways) {}

    public function for(string $name): BillingGateway
    {
        if (! isset($this->gateways[$name])) {
            throw new InvalidArgumentException("The billing gateway \"{$name}\" does not exist.");
        }

        return $this->gateways[$name];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->gateways);
    }
}
