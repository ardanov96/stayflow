<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Subscription;

use InvalidArgumentException;

/**
 * A plan of the product.
 *
 * A plan says which modules the organization has and which features are
 * switched on. The modules are the ones of the URL of the API
 * (`api/<module>/...`), so a module that is not in the plan is not
 * reachable, by route and by API.
 */
final readonly class Plan
{
    /**
     * @param  list<string>  $modules
     * @param  list<string>  $features
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $modules,
        public array $features,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromConfig(string $key, array $definition): self
    {
        if (! isset($definition['label'], $definition['modules'], $definition['features'])) {
            throw new InvalidArgumentException("The plan \"{$key}\" is not defined correctly.");
        }

        return new self(
            key: $key,
            label: (string) $definition['label'],
            modules: array_values(array_map('strval', (array) $definition['modules'])),
            features: array_values(array_map('strval', (array) $definition['features'])),
        );
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modules, true);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features, true);
    }
}
