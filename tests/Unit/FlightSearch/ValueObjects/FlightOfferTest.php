<?php

use Tests\Helpers\FlightOfferFactory;

test('toArray returns correct shape', function () {
    $offer = FlightOfferFactory::make();

    $result = $offer->toArray();

    expect($result)->toHaveKeys([
        'id', 'carrier', 'origin', 'destination',
        'departure', 'arrival', 'stops', 'price',
        'currency', 'flight_number', 'provider',
    ])
        ->and($result['flight_number'])->toBe('EK585')
        ->and($result['currency'])->toBe('USD');
});

test('toArray uses snake_case for flight_number', function () {
    $offer = FlightOfferFactory::make();

    $result = $offer->toArray();

    expect(array_key_exists('flight_number', $result))->toBeTrue()
        ->and(array_key_exists('flightNumber', $result))->toBeFalse();
});
