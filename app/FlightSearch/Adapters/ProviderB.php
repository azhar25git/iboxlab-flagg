<?php

namespace App\FlightSearch\Adapters;

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\ValueObjects\FlightOffer;
use Carbon\Carbon;

class ProviderB implements ProviderContract
{
    public function name(): string
    {
        return 'ProviderB';
    }

    public function endpoint(): string
    {
        return '/api/internal/providers/ProviderB/fixtures';
    }

    public function responseKey(): string
    {
        return 'data';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function normalize(array $raw): FlightOffer
    {
        $departure = Carbon::createFromFormat('Y-m-d H:i', (string) data_get($raw, 'departure_time'), 'UTC')
            ->toIso8601String();
        $arrival = Carbon::createFromFormat('Y-m-d H:i', (string) data_get($raw, 'arrival_time'), 'UTC')
            ->toIso8601String();

        $carrier = (string) data_get($raw, 'airline_code');
        $flightNumber = (string) data_get($raw, 'number');
        $origin = (string) data_get($raw, 'origin');
        $destination = (string) data_get($raw, 'destination');

        $id = FlightOffer::makeId(
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
            stops: (int) data_get($raw, 'segments'),
            price: (float) data_get($raw, 'price.amount'),
            currency: (string) data_get($raw, 'price.currency'),
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
                'airline_code' => 'BS', 'origin' => 'DAC', 'destination' => 'DXB',
                'departure_time' => '2026-07-01 09:15', 'arrival_time' => '2026-07-01 15:00',
                'segments' => 1, 'price' => ['amount' => 295, 'currency' => 'USD'], 'number' => 'BS220',
            ],
            [
                'airline_code' => 'BS', 'origin' => 'DAC', 'destination' => 'DXB',
                'departure_time' => '2026-07-01 14:30', 'arrival_time' => '2026-07-01 19:20',
                'segments' => 1, 'price' => ['amount' => 265, 'currency' => 'USD'], 'number' => 'BS118',
            ],
            [
                'airline_code' => 'EK', 'origin' => 'DAC', 'destination' => 'DXB',
                'departure_time' => '2026-07-01 03:45', 'arrival_time' => '2026-07-01 06:50',
                'segments' => 0, 'price' => ['amount' => 399, 'currency' => 'USD'], 'number' => 'EK585',
            ],
        ];
    }
}
