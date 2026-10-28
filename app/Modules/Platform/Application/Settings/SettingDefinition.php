<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Settings;

use InvalidArgumentException;

/**
 * The definition of one setting: its type, its default and its scope.
 *
 * A value that does not match the type is refused, and a setting that
 * does not exist is refused too: the application never invents a setting.
 */
final readonly class SettingDefinition
{
    public const SCOPE_ORGANIZATION = 'organization';

    public const SCOPE_PROPERTY = 'property';

    /**
     * @param  list<string>  $choices
     */
    public function __construct(
        public string $key,
        public string $type,
        public mixed $default,
        public string $scope,
        public array $choices = [],
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromConfig(string $key, array $definition): self
    {
        if (! isset($definition['type'], $definition['default'], $definition['scope'])) {
            throw new InvalidArgumentException("The setting \"{$key}\" is not defined correctly.");
        }

        return new self(
            key: $key,
            type: (string) $definition['type'],
            default: $definition['default'],
            scope: (string) $definition['scope'],
            choices: array_values(array_map('strval', (array) ($definition['choices'] ?? []))),
        );
    }

    /**
     * Whether the value belongs to this setting.
     */
    public function accepts(mixed $value): bool
    {
        $valid = match ($this->type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'bool' => is_bool($value),
            'decimal' => is_int($value) || is_float($value),
            default => false,
        };

        if (! $valid) {
            return false;
        }

        if ($this->choices !== [] && ! in_array((string) $value, $this->choices, true)) {
            return false;
        }

        return true;
    }

    /**
     * The value, cast to the type of the setting.
     */
    public function cast(mixed $value): mixed
    {
        return match ($this->type) {
            'string' => (string) $value,
            'int' => (int) $value,
            'bool' => (bool) $value,
            'decimal' => (float) $value,
            default => $value,
        };
    }
}
