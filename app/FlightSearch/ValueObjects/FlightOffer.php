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

    public static function makeId(
        string $carrier,
        string $flightNumber,
        string $origin,
        string $destination,
        string $departureUtc,
    ): string {
        return hash('sha256', implode('|', [
            strtoupper($carrier),
            strtoupper($flightNumber),
            strtoupper($origin),
            strtoupper($destination),
            $departureUtc,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            carrier: (string) $data['carrier'],
            origin: (string) $data['origin'],
            destination: (string) $data['destination'],
            departure: (string) $data['departure'],
            arrival: (string) $data['arrival'],
            stops: (int) $data['stops'],
            price: (float) $data['price'],
            currency: (string) $data['currency'],
            flightNumber: (string) $data['flight_number'],
            provider: (string) $data['provider'],
            providerRawId: (string) $data['provider_raw_id'],
        );
    }

    public function durationMinutes(): int
    {
        $dep = Carbon::parse($this->departure);
        $arr = Carbon::parse($this->arrival);

        return (int) $dep->diffInMinutes($arr);
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
    public function toCacheArray(): array
    {
        return array_merge($this->toArray(), [
            'provider_raw_id' => $this->providerRawId,
        ]);
    }
}
