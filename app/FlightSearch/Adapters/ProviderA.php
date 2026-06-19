<?php

namespace App\FlightSearch\Adapters;

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Services\FlightIdGenerator;
use App\FlightSearch\ValueObjects\FlightOffer;
use Carbon\Carbon;

class ProviderA implements ProviderContract
{
    public function __construct(
        private readonly FlightIdGenerator $idGenerator,
    ) {}

    public function name(): string
    {
        return 'ProviderA';
    }

    public function endpoint(): string
    {
        return '/api/internal/providers/ProviderA/fixtures';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function normalize(array $raw): FlightOffer
    {
        $departure = Carbon::parse((string) data_get($raw, 'depart'), 'UTC')->toIso8601String();
        $arrival = Carbon::parse((string) data_get($raw, 'arrive'), 'UTC')->toIso8601String();

        $carrier = (string) data_get($raw, 'carrier');
        $flightNumber = (string) data_get($raw, 'flight_no');
        $origin = (string) data_get($raw, 'from');
        $destination = (string) data_get($raw, 'to');

        $id = $this->idGenerator->generate(
            carrier: $carrier,
            flightNumber: $flightNumber,
            origin: $origin,
            destination: $destination,
            departureUtc: $departure,
        );

        return new FlightOffer(
            id: $id,
            carrier: $carrier,
            origin: $origin,
            destination: $destination,
            departure: $departure,
            arrival: $arrival,
            stops: (int) data_get($raw, 'stops'),
            price: (float) data_get($raw, 'fare_usd'),
            currency: 'USD',
            flightNumber: $flightNumber,
            provider: $this->name(),
            providerRawId: $flightNumber,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fixtures(): array
    {
        return [
            [
                'carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB',
                'depart' => '2026-07-01T08:00:00', 'arrive' => '2026-07-01T12:30:00',
                'stops' => 0, 'fare_usd' => 320.00, 'flight_no' => 'AA101',
            ],
            [
                'carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB',
                'depart' => '2026-07-01T22:10:00', 'arrive' => '2026-07-02T02:40:00',
                'stops' => 0, 'fare_usd' => 280.00, 'flight_no' => 'AA205',
            ],
            [
                'carrier' => 'BS', 'from' => 'DAC', 'to' => 'DXB',
                'depart' => '2026-07-01T09:15:00', 'arrive' => '2026-07-01T15:00:00',
                'stops' => 1, 'fare_usd' => 310.00, 'flight_no' => 'BS220',
            ],
            [
                'carrier' => 'EK', 'from' => 'DAC', 'to' => 'DXB',
                'depart' => '2026-07-01T03:45:00', 'arrive' => '2026-07-01T06:50:00',
                'stops' => 0, 'fare_usd' => 410.00, 'flight_no' => 'EK585',
            ],
        ];
    }
}
