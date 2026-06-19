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
    );

    $result = $response->toArray();

    expect($result)->toHaveKeys(['data', 'meta'])
        ->and($result['data'])->toBeArray()->toHaveCount(1)
        ->and($result['meta'])->toHaveKeys(['providers', 'total_offers', 'unique_flights'])
        ->and($result['meta']['total_offers'])->toBe(1)
        ->and($result['meta']['unique_flights'])->toBe(1);
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
