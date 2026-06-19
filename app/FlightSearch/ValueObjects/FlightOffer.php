<?php

namespace App\FlightSearch\ValueObjects;

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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'carrier' => $this->carrier,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'departure' => $this->departure,
            'arrival' => $this->arrival,
            'stops' => $this->stops,
            'price' => $this->price,
            'currency' => $this->currency,
            'flight_number' => $this->flightNumber,
            'provider' => $this->provider,
        ];
    }
}
