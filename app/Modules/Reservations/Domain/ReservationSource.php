<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain;

use InvalidArgumentException;

/**
 * Where a reservation comes from.
 *
 * The source tells the application who may change the reservation and
 * how it was paid: the direct channel of the booking engine, the staff
 * of the hotel, or an OTA through a channel of the distribution module.
 */
final readonly class ReservationSource
{
    private function __construct(
        public string $value,
        public bool $isOta,
        public ?string $channel = null,
    ) {}

    public static function direct(): self
    {
        return new self('direct', false);
    }

    public static function pms(): self
    {
        return new self('pms', false);
    }

    /**
     * A reservation that came from an OTA through a channel.
     */
    public static function ota(string $channel): self
    {
        if ($channel === '') {
            throw new InvalidArgumentException('The channel of an OTA reservation must not be empty.');
        }

        return new self('ota:'.$channel, true, $channel);
    }

    /**
     * The source, as it is stored.
     */
    public static function fromString(string $value): self
    {
        if (str_starts_with($value, 'ota:')) {
            return self::ota(substr($value, 4));
        }

        return match ($value) {
            'direct' => self::direct(),
            'pms' => self::pms(),
            default => throw new InvalidArgumentException("The source \"{$value}\" does not exist."),
        };
    }
}
