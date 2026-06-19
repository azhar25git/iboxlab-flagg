<?php

namespace App\FlightSearch\Enums;

enum SortField: string
{
    case PRICE = 'price';
    case DEPARTURE = 'departure';
    case ARRIVAL = 'arrival';
    case STOPS = 'stops';
    case DURATION = 'duration';

    public static function fromString(string $value): self
    {
        return match ($value) {
            'price' => self::PRICE,
            'departure' => self::DEPARTURE,
            'arrival' => self::ARRIVAL,
            'stops' => self::STOPS,
            'duration' => self::DURATION,
            default => throw new \InvalidArgumentException("Invalid sort field: {$value}"),
        };
    }
}
