<?php

use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\ValueObjects\ProviderResultSet;
use App\FlightSearch\ValueObjects\SearchResponse;
use Tests\Helpers\FlightOfferFactory;

test('toArray includes data and meta', function () {
    $offer = FlightOfferFactory::make();
    $resultSet = new ProviderResultSet(
        providerName: 'ProviderB',
        offers: [$offer],
        status: ProviderStatus::SUCCESS,
        durationMs: 15,
    );

    $response = new SearchResponse(
        flights: [$offer],
        providerResults: [$resultSet],
        passengers: 2,
    );

    $result = $response->toArray();

    expect($result)->toHaveKeys(['data', 'meta'])
        ->and($result['data'])->toBeArray()->toHaveCount(1)
        ->and($result['meta'])->toHaveKeys([
            'providers', 'total_offers', 'unique_flights',
            'passengers', 'currency', 'price_unit',
        ])
        ->and($result['meta']['total_offers'])->toBe(1)
        ->and($result['meta']['unique_flights'])->toBe(1)
        ->and($result['meta']['passengers'])->toBe(2)
        ->and($result['meta']['currency'])->toBe('USD')
        ->and($result['meta']['price_unit'])->toBe('per_passenger');
});

test('includes total_price per flight based on passenger count', function () {
    $offer = FlightOfferFactory::make(['price' => 265.00]);
    $resultSet = new ProviderResultSet('ProviderB', [$offer], ProviderStatus::SUCCESS, durationMs: 5);

    $response = new SearchResponse(
        flights: [$offer],
        providerResults: [$resultSet],
        passengers: 2,
    );

    $result = $response->toArray();

    expect($result['data'][0]['total_price'])->toBe(530.0);
});

test('total_price equals price when single passenger', function () {
    $offer = FlightOfferFactory::make(['price' => 399.00]);
    $resultSet = new ProviderResultSet('ProviderB', [$offer], ProviderStatus::SUCCESS, durationMs: 5);

    $response = new SearchResponse(
        flights: [$offer],
        providerResults: [$resultSet],
        passengers: 1,
    );

    $result = $response->toArray();

    expect($result['data'][0]['total_price'])->toBe(399.0);
});

test('meta shows provider status', function () {
    $offer = FlightOfferFactory::make();
    $resultSet = new ProviderResultSet(
        providerName: 'ProviderA',
        offers: [$offer],
        status: ProviderStatus::SUCCESS,
        durationMs: 10,
    );

    $response = new SearchResponse(
        flights: [$offer],
        providerResults: [$resultSet],
    );

    $result = $response->toArray();

    $provider = $result['meta']['providers'][0];
    expect($provider['name'])->toBe('ProviderA')
        ->and($provider['status'])->toBe('success')
        ->and($provider['offers'])->toBe(1)
        ->and($provider['duration_ms'])->toBe(10);
});
