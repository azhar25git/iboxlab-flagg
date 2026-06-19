<?php

namespace App\FlightSearch\Adapters;

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Services\FlightIdGenerator;
use App\FlightSearch\ValueObjects\FlightOffer;
use Carbon\Carbon;

class ProviderC implements ProviderContract
{
    public function __construct(
        private readonly FlightIdGenerator $idGenerator,
    ) {}

    public function name(): string
    {
        return 'ProviderC';
    }

    public function endpoint(): string
    {
        return '/api/internal/providers/ProviderC/fixtures';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function normalize(array $raw): FlightOffer
    {
        $departure = Carbon::createFromTimestamp((int) data_get($raw, 'times.dep'), 'UTC')->toIso8601String();
        $arrival = Carbon::createFromTimestamp((int) data_get($raw, 'times.arr'), 'UTC')->toIso8601String();

        $carrier = (string) data_get($raw, 'iata');
        $flightNumber = (string) data_get($raw, 'code');
        $origin = (string) data_get($raw, 'route.src');
        $destination = (string) data_get($raw, 'route.dst');

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
            stops: (int) data_get($raw, 'layovers'),
            price: (float) data_get($raw, 'total_price'),
            currency: (string) data_get($raw, 'currency'),
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
                'iata' => 'AA', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
                'times' => ['dep' => 1782892800, 'arr' => 1782909000],
                'layovers' => 0, 'total_price' => 335, 'currency' => 'USD', 'code' => 'AA101',
            ],
            [
                'iata' => 'CJ', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
                'times' => ['dep' => 1782885600, 'arr' => 1782903600],
                'layovers' => 2, 'total_price' => 270, 'currency' => 'USD', 'code' => 'CJ300',
            ],
            [
                'iata' => 'EK', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
                'times' => ['dep' => 1782877500, 'arr' => 1782888600],
                'layovers' => 0, 'total_price' => 405, 'currency' => 'USD', 'code' => 'EK585',
            ],
        ];
    }
}
