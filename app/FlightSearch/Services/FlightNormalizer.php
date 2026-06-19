<?php

namespace App\FlightSearch\Services;

use App\FlightSearch\ValueObjects\FlightOffer;
use Carbon\Carbon;

class FlightNormalizer
{
    public function __construct(
        private readonly FlightIdGenerator $idGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public function normalizeProviderA(array $raw): FlightOffer
    {
        $departure = Carbon::parse($raw['depart'], 'UTC')->toIso8601String();
        $arrival = Carbon::parse($raw['arrive'], 'UTC')->toIso8601String();

        $id = $this->idGenerator->generate(
            carrier: $raw['carrier'],
            flightNumber: $raw['flight_no'],
            origin: $raw['from'],
            destination: $raw['to'],
            departureUtc: $departure,
        );

        return new FlightOffer(
            id: $id,
            carrier: $raw['carrier'],
            origin: $raw['from'],
            destination: $raw['to'],
            departure: $departure,
            arrival: $arrival,
            stops: $raw['stops'],
            price: (float) $raw['fare_usd'],
            currency: 'USD',
            flightNumber: $raw['flight_no'],
            provider: 'ProviderA',
            providerRawId: $raw['flight_no'],
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function normalizeProviderB(array $raw): FlightOffer
    {
        $departure = Carbon::createFromFormat('Y-m-d H:i', $raw['departure_time'], 'UTC')
            ->toIso8601String();
        $arrival = Carbon::createFromFormat('Y-m-d H:i', $raw['arrival_time'], 'UTC')
            ->toIso8601String();

        $id = $this->idGenerator->generate(
            carrier: $raw['airline_code'],
            flightNumber: $raw['number'],
            origin: $raw['origin'],
            destination: $raw['destination'],
            departureUtc: $departure,
        );

        return new FlightOffer(
            id: $id,
            carrier: $raw['airline_code'],
            origin: $raw['origin'],
            destination: $raw['destination'],
            departure: $departure,
            arrival: $arrival,
            stops: $raw['segments'],
            price: (float) $raw['price']['amount'],
            currency: $raw['price']['currency'],
            flightNumber: $raw['number'],
            provider: 'ProviderB',
            providerRawId: $raw['number'],
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function normalizeProviderC(array $raw): FlightOffer
    {
        $departure = Carbon::createFromTimestamp($raw['times']['dep'], 'UTC')->toIso8601String();
        $arrival = Carbon::createFromTimestamp($raw['times']['arr'], 'UTC')->toIso8601String();

        $id = $this->idGenerator->generate(
            carrier: $raw['iata'],
            flightNumber: $raw['code'],
            origin: $raw['route']['src'],
            destination: $raw['route']['dst'],
            departureUtc: $departure,
        );

        return new FlightOffer(
            id: $id,
            carrier: $raw['iata'],
            origin: $raw['route']['src'],
            destination: $raw['route']['dst'],
            departure: $departure,
            arrival: $arrival,
            stops: $raw['layovers'],
            price: (float) $raw['total_price'],
            currency: $raw['currency'],
            flightNumber: $raw['code'],
            provider: 'ProviderC',
            providerRawId: $raw['code'],
        );
    }
}
