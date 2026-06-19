<?php

namespace Tests\Helpers;

use App\FlightSearch\Services\FlightIdGenerator;
use App\FlightSearch\ValueObjects\FlightOffer;

class FlightOfferFactory
{
    private static ?FlightIdGenerator $idGenerator = null;

    private static function idGen(): FlightIdGenerator
    {
        if (self::$idGenerator === null) {
            self::$idGenerator = new FlightIdGenerator;
        }

        return self::$idGenerator;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function make(array $overrides = []): FlightOffer
    {
        $attrs = array_merge([
            'carrier' => 'EK',
            'origin' => 'DAC',
            'destination' => 'DXB',
            'departure' => '2026-07-01T03:45:00+00:00',
            'arrival' => '2026-07-01T06:50:00+00:00',
            'stops' => 0,
            'price' => 399.00,
            'currency' => 'USD',
            'flightNumber' => 'EK585',
            'provider' => 'ProviderB',
            'providerRawId' => 'EK585',
        ], $overrides);

        $id = self::idGen()->generate(
            carrier: $attrs['carrier'],
            flightNumber: $attrs['flightNumber'],
            origin: $attrs['origin'],
            destination: $attrs['destination'],
            departureUtc: $attrs['departure'],
        );

        return new FlightOffer(
            id: $attrs['id'] ?? $id,
            carrier: $attrs['carrier'],
            origin: $attrs['origin'],
            destination: $attrs['destination'],
            departure: $attrs['departure'],
            arrival: $attrs['arrival'],
            stops: $attrs['stops'],
            price: $attrs['price'],
            currency: $attrs['currency'],
            flightNumber: $attrs['flightNumber'],
            provider: $attrs['provider'],
            providerRawId: $attrs['providerRawId'],
        );
    }
}
