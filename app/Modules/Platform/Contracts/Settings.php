<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

/**
 * The settings of an organization and of its properties (task P1-13).
 *
 * A setting that is not defined does not exist, and a value that does not
 * match the type of the setting is refused. Every change is audited.
 */
interface Settings
{
    /**
     * The value of a setting, or its default.
     */
    public function get(string $key, ?int $propertyId = null): mixed;

    public function getInt(string $key, ?int $propertyId = null): int;

    public function getString(string $key, ?int $propertyId = null): string;

    public function getBool(string $key, ?int $propertyId = null): bool;

    /**
     * Write a setting. The value is validated, the change is audited and
     * the cache is invalidated.
     */
    public function set(string $key, mixed $value, ?int $propertyId = null, ?int $actorId = null): void;

    /**
     * Every setting of the scope, with the values that are set and the
     * defaults of the others.
     *
     * @return array<string, mixed>
     */
    public function all(?int $propertyId = null): array;
}
