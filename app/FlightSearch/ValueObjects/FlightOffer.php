<?php

namespace App\FlightSearch\ValueObjects;

use Carbon\Carbon;

readonly class FlightOffer
{
    public function __construct(
        public string $id,
        public string $carrier,
        public string $origin,
        public string $destination,
        public string $departure,
        public string $arrival,
        public int $stops,
        public float $price,
        public string $currency,
        public string $flightNumber,
        public string $provider,
        public string $providerRawId,
    ) {}

    public function durationMinutes(): int
    {
        $dep = Carbon::parse($this->departure);
        $arr = Carbon::parse($this->arrival);

        return (int) $dep->diffInMinutes($arr);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'carrier' => $this->carrier,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'departure' => $this->departure,
            'arrival' => $this->arrival,
            'duration_minutes' => $this->durationMinutes(),
            'stops' => $this->stops,
            'price' => $this->price,
            'currency' => $this->currency,
            'flight_number' => $this->flightNumber,
            'provider' => $this->provider,
        ];
    }
}
