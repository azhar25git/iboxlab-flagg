<?php

use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Services\FlightOfferRepository;
use App\FlightSearch\Services\ProviderDispatcher;
use App\FlightSearch\Services\SearchService;
use App\FlightSearch\ValueObjects\FlightOffer;
use App\FlightSearch\ValueObjects\ProviderResultSet;
use App\FlightSearch\ValueObjects\SearchRequest;
use Tests\Helpers\FlightOfferFactory;

function makeOffer(array $overrides = []): FlightOffer
{
    return FlightOfferFactory::make($overrides);
}

function makeSearchRequest(array $overrides = []): SearchRequest
{
    return new SearchRequest(
        from: 'DAC',
        to: 'DXB',
        date: '2026-07-01',
        passengers: 2,
        sortField: $overrides['sortField'] ?? null,
        sortDirection: $overrides['sortDirection'] ?? null,
        filterStops: $overrides['filterStops'] ?? null,
        filterCarrier: $overrides['filterCarrier'] ?? null,
        filterMaxPrice: $overrides['filterMaxPrice'] ?? null,
    );
}

function makeDispatcherWithOffers(array $providerOffers): ProviderDispatcher
{
    $results = [];

    foreach ($providerOffers as $name => $offers) {
        $results[] = new ProviderResultSet(
            providerName: $name,
            offers: $offers,
            status: ProviderStatus::SUCCESS,
            durationMs: 5,
        );
    }

    $dispatcher = mock(ProviderDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->andReturn($results);

    return $dispatcher;
}

function makeSearchService(ProviderDispatcher $dispatcher): SearchService
{
    return new SearchService($dispatcher, app(FlightOfferRepository::class));
}

describe('deduplication', function () {
    test('keeps cheapest offer when same flight from multiple providers', function () {
        $offerA = makeOffer(['provider' => 'ProviderA', 'price' => 410.00]);
        $offerB = makeOffer(['provider' => 'ProviderB', 'price' => 399.00]);
        $offerC = makeOffer(['provider' => 'ProviderC', 'price' => 405.00]);

        $dispatcher = makeDispatcherWithOffers([
            'ProviderA' => [$offerA],
            'ProviderB' => [$offerB],
            'ProviderC' => [$offerC],
        ]);

        $service = makeSearchService($dispatcher);
        $response = $service->search(makeSearchRequest());

        expect($response->flights)->toHaveCount(1)
            ->and($response->flights[0]->price)->toBe(399.00)
            ->and($response->flights[0]->provider)->toBe('ProviderB');
    });

    test('keeps distinct flights', function () {
        $offer1 = makeOffer(['flightNumber' => 'AA101', 'carrier' => 'AA', 'price' => 300]);
        $offer2 = makeOffer(['flightNumber' => 'AA205', 'carrier' => 'AA', 'price' => 250]);

        $dispatcher = makeDispatcherWithOffers([
            'ProviderA' => [$offer1, $offer2],
        ]);

        $service = makeSearchService($dispatcher);
        $response = $service->search(makeSearchRequest());

        expect($response->flights)->toHaveCount(2);
    });
});

describe('sorting', function () {
    test('default sort is price ascending', function () {
        $offer1 = makeOffer(['price' => 400, 'flightNumber' => 'AA101', 'carrier' => 'AA']);
        $offer2 = makeOffer(['price' => 200, 'flightNumber' => 'AA205', 'carrier' => 'AA']);

        $dispatcher = makeDispatcherWithOffers([
            'ProviderA' => [$offer1, $offer2],
        ]);

        $service = makeSearchService($dispatcher);
        $response = $service->search(makeSearchRequest());

        expect($response->flights[0]->price)->toBe(200.0)
            ->and($response->flights[1]->price)->toBe(400.0);
    });

    test('sorts by price descending', function () {
        $offer1 = makeOffer(['price' => 200, 'flightNumber' => 'AA101', 'carrier' => 'AA']);
        $offer2 = makeOffer(['price' => 400, 'flightNumber' => 'AA205', 'carrier' => 'AA']);

        $dispatcher = makeDispatcherWithOffers([
            'ProviderA' => [$offer1, $offer2],
        ]);

        $service = makeSearchService($dispatcher);
        $response = $service->search(makeSearchRequest([
            'sortField' => 'price',
            'sortDirection' => 'desc',
        ]));

        expect($response->flights[0]->price)->toBe(400.0)
            ->and($response->flights[1]->price)->toBe(200.0);
    });

    test('sorts by stops', function () {
        $offer1 = makeOffer(['stops' => 2, 'price' => 100, 'flightNumber' => 'AA101', 'carrier' => 'AA']);
        $offer2 = makeOffer(['stops' => 0, 'price' => 200, 'flightNumber' => 'AA205', 'carrier' => 'AA']);
        $offer3 = makeOffer(['stops' => 1, 'price' => 150, 'flightNumber' => 'AA301', 'carrier' => 'AA']);

        $dispatcher = makeDispatcherWithOffers([
            'ProviderA' => [$offer1, $offer2, $offer3],
        ]);

        $service = makeSearchService($dispatcher);
        $response = $service->search(makeSearchRequest([
            'sortField' => 'stops',
            'sortDirection' => 'asc',
        ]));

        expect($response->flights[0]->stops)->toBe(0)
            ->and($response->flights[1]->stops)->toBe(1)
            ->and($response->flights[2]->stops)->toBe(2);
    });
});

describe('filtering', function () {
    test('filters by max stops', function () {
        $direct = makeOffer(['stops' => 0, 'flightNumber' => 'AA101', 'carrier' => 'AA', 'price' => 300]);
        $oneStop = makeOffer(['stops' => 1, 'flightNumber' => 'AA205', 'carrier' => 'AA', 'price' => 250]);

        $dispatcher = makeDispatcherWithOffers([
            'ProviderA' => [$direct, $oneStop],
        ]);

        $service = makeSearchService($dispatcher);
        $response = $service->search(makeSearchRequest(['filterStops' => 0]));

        expect($response->flights)->toHaveCount(1)
            ->and($response->flights[0]->stops)->toBe(0);
    });

    test('filters by carrier', function () {
        $ek = makeOffer(['carrier' => 'EK', 'flightNumber' => 'EK585', 'price' => 400]);
        $aa = makeOffer(['carrier' => 'AA', 'flightNumber' => 'AA101', 'price' => 300]);

        $dispatcher = makeDispatcherWithOffers([
            'ProviderA' => [$ek, $aa],
        ]);

        $service = makeSearchService($dispatcher);
        $response = $service->search(makeSearchRequest(['filterCarrier' => 'AA']));

        expect($response->flights)->toHaveCount(1)
            ->and($response->flights[0]->carrier)->toBe('AA');
    });

    test('filters by max price', function () {
        $cheap = makeOffer(['price' => 200, 'flightNumber' => 'AA101', 'carrier' => 'AA']);
        $expensive = makeOffer(['price' => 500, 'flightNumber' => 'AA205', 'carrier' => 'AA']);

        $dispatcher = makeDispatcherWithOffers([
            'ProviderA' => [$cheap, $expensive],
        ]);

        $service = makeSearchService($dispatcher);
        $response = $service->search(makeSearchRequest(['filterMaxPrice' => 300.0]));

        expect($response->flights)->toHaveCount(1)
            ->and($response->flights[0]->price)->toBe(200.0);
    });
});

describe('error isolation', function () {
    test('single provider failure does not crash search', function () {
        $offer = makeOffer();

        $dispatcher = mock(ProviderDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturn([
            new ProviderResultSet('GoodProvider', [$offer], ProviderStatus::SUCCESS, durationMs: 5),
            new ProviderResultSet('BrokenProvider', [], ProviderStatus::ERROR, durationMs: 0, errorMessage: 'Provider request failed.'),
        ]);

        $service = makeSearchService($dispatcher);
        $response = $service->search(makeSearchRequest());

        expect($response->flights)->toHaveCount(1);

        $goodMeta = collect($response->toArray()['meta']['providers'])
            ->first(fn ($p) => $p['name'] === 'GoodProvider');

        $brokenMeta = collect($response->toArray()['meta']['providers'])
            ->first(fn ($p) => $p['name'] === 'BrokenProvider');

        expect($goodMeta['status'])->toBe('success')
            ->and($brokenMeta['status'])->toBe('error')
            ->and($brokenMeta['error_message'])->toBe('Provider request failed.')
            ->and($brokenMeta['error_message'])->not->toContain('Connection refused');
    });
});
