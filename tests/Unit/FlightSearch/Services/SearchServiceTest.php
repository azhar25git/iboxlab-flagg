<?php

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Services\SearchService;
use App\FlightSearch\ValueObjects\FlightOffer;
use Tests\Helpers\FlightOfferFactory;

function makeOffer(array $overrides = []): FlightOffer
{
    return FlightOfferFactory::make($overrides);
}

function makeParams(array $overrides = []): array
{
    return array_merge([
        'from' => 'DAC',
        'to' => 'DXB',
        'date' => '2026-07-01',
        'passengers' => 2,
        'sortField' => null,
        'sortDirection' => null,
        'filterMaxStops' => null,
        'filterCarrier' => null,
        'filterMaxPrice' => null,
    ], $overrides);
}

function makeProvider(string $name, array $offers): ProviderContract
{
    $provider = mock(ProviderContract::class);
    $provider->shouldReceive('name')->andReturn($name);
    $provider->shouldReceive('endpoint')->andReturn('/api/internal/test');
    $provider->shouldReceive('fixtures')->andReturn($offers);
    $provider->shouldReceive('normalize')->andReturnUsing(
        fn (array $raw) => FlightOfferFactory::make($raw),
    );

    return $provider;
}

function makeSearchService(ProviderContract ...$providers): SearchService
{
    $map = [];
    foreach ($providers as $p) {
        $map[$p->name()] = $p;
    }

    return new SearchService($map);
}

describe('deduplication', function () {
    test('keeps cheapest offer when same flight from multiple providers', function () {
        $offerA = makeOffer(['provider' => 'ProviderA', 'price' => 410.00]);
        $offerB = makeOffer(['provider' => 'ProviderB', 'price' => 399.00]);
        $offerC = makeOffer(['provider' => 'ProviderC', 'price' => 405.00]);

        $provider = mock(ProviderContract::class);
        $provider->shouldReceive('name')->andReturn('MultiProvider');
        $provider->shouldReceive('endpoint')->andReturn('/api/internal/test');
        $provider->shouldReceive('fixtures')->andReturn([]);
        $provider->shouldReceive('normalize')->times(0);

        $service = new SearchService(['MultiProvider' => $provider]);
        $service = mock(SearchService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLocal')->andReturn([
            [
                'provider_name' => 'MultiProvider',
                'offers' => [$offerA, $offerB, $offerC],
                'status' => ProviderStatus::SUCCESS,
                'duration_ms' => 5,
                'error_message' => null,
            ],
        ]);
        $service->shouldReceive('resolveRemote')->andReturn([]);

        $result = $service->search(makeParams());

        expect($result['flights'])->toHaveCount(1)
            ->and($result['flights'][0]->price)->toBe(399.00)
            ->and($result['flights'][0]->provider)->toBe('ProviderB');
    });

    test('keeps distinct flights', function () {
        $offer1 = makeOffer(['flightNumber' => 'AA101', 'carrier' => 'AA', 'price' => 300]);
        $offer2 = makeOffer(['flightNumber' => 'AA205', 'carrier' => 'AA', 'price' => 250]);

        $provider = mock(ProviderContract::class);
        $provider->shouldReceive('name')->andReturn('P');
        $provider->shouldReceive('endpoint')->andReturn('/api/internal/test');
        $provider->shouldReceive('fixtures')->andReturn([]);
        $provider->shouldReceive('normalize')->times(0);

        $service = mock(SearchService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLocal')->andReturn([
            [
                'provider_name' => 'P',
                'offers' => [$offer1, $offer2],
                'status' => ProviderStatus::SUCCESS,
                'duration_ms' => 5,
                'error_message' => null,
            ],
        ]);
        $service->shouldReceive('resolveRemote')->andReturn([]);

        $result = $service->search(makeParams());

        expect($result['flights'])->toHaveCount(2);
    });
});

describe('sorting', function () {
    function serviceWithOffers(array $offers): SearchService
    {
        $service = mock(SearchService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLocal')->andReturn([
            [
                'provider_name' => 'P',
                'offers' => $offers,
                'status' => ProviderStatus::SUCCESS,
                'duration_ms' => 5,
                'error_message' => null,
            ],
        ]);
        $service->shouldReceive('resolveRemote')->andReturn([]);

        return $service;
    }

    test('default sort is price ascending', function () {
        $offer1 = makeOffer(['price' => 400, 'flightNumber' => 'AA101', 'carrier' => 'AA']);
        $offer2 = makeOffer(['price' => 200, 'flightNumber' => 'AA205', 'carrier' => 'AA']);

        $result = serviceWithOffers([$offer1, $offer2])->search(makeParams());

        expect($result['flights'][0]->price)->toBe(200.0)
            ->and($result['flights'][1]->price)->toBe(400.0);
    });

    test('sorts by price descending', function () {
        $offer1 = makeOffer(['price' => 200, 'flightNumber' => 'AA101', 'carrier' => 'AA']);
        $offer2 = makeOffer(['price' => 400, 'flightNumber' => 'AA205', 'carrier' => 'AA']);

        $result = serviceWithOffers([$offer1, $offer2])->search(makeParams([
            'sortField' => 'price',
            'sortDirection' => 'desc',
        ]));

        expect($result['flights'][0]->price)->toBe(400.0)
            ->and($result['flights'][1]->price)->toBe(200.0);
    });

    test('sorts by stops', function () {
        $offer1 = makeOffer(['stops' => 2, 'price' => 100, 'flightNumber' => 'AA101', 'carrier' => 'AA']);
        $offer2 = makeOffer(['stops' => 0, 'price' => 200, 'flightNumber' => 'AA205', 'carrier' => 'AA']);
        $offer3 = makeOffer(['stops' => 1, 'price' => 150, 'flightNumber' => 'AA301', 'carrier' => 'AA']);

        $result = serviceWithOffers([$offer1, $offer2, $offer3])->search(makeParams([
            'sortField' => 'stops',
            'sortDirection' => 'asc',
        ]));

        expect($result['flights'][0]->stops)->toBe(0)
            ->and($result['flights'][1]->stops)->toBe(1)
            ->and($result['flights'][2]->stops)->toBe(2);
    });
});

describe('filtering', function () {
    function filteredServiceWithOffers(array $offers, array $params): array
    {
        $service = mock(SearchService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLocal')->andReturn([
            [
                'provider_name' => 'P',
                'offers' => $offers,
                'status' => ProviderStatus::SUCCESS,
                'duration_ms' => 5,
                'error_message' => null,
            ],
        ]);
        $service->shouldReceive('resolveRemote')->andReturn([]);

        return $service->search($params);
    }

    test('filters by max stops', function () {
        $direct = makeOffer(['stops' => 0, 'flightNumber' => 'AA101', 'carrier' => 'AA', 'price' => 300]);
        $oneStop = makeOffer(['stops' => 1, 'flightNumber' => 'AA205', 'carrier' => 'AA', 'price' => 250]);

        $result = filteredServiceWithOffers([$direct, $oneStop], makeParams(['filterMaxStops' => 0]));

        expect($result['flights'])->toHaveCount(1)
            ->and($result['flights'][0]->stops)->toBe(0);
    });

    test('filters by carrier', function () {
        $ek = makeOffer(['carrier' => 'EK', 'flightNumber' => 'EK585', 'price' => 400]);
        $aa = makeOffer(['carrier' => 'AA', 'flightNumber' => 'AA101', 'price' => 300]);

        $result = filteredServiceWithOffers([$ek, $aa], makeParams(['filterCarrier' => 'AA']));

        expect($result['flights'])->toHaveCount(1)
            ->and($result['flights'][0]->carrier)->toBe('AA');
    });

    test('filters by max price', function () {
        $cheap = makeOffer(['price' => 200, 'flightNumber' => 'AA101', 'carrier' => 'AA']);
        $expensive = makeOffer(['price' => 500, 'flightNumber' => 'AA205', 'carrier' => 'AA']);

        $result = filteredServiceWithOffers([$cheap, $expensive], makeParams(['filterMaxPrice' => 300.0]));

        expect($result['flights'])->toHaveCount(1)
            ->and($result['flights'][0]->price)->toBe(200.0);
    });
});

describe('error isolation', function () {
    test('single provider failure does not crash search', function () {
        $offer = makeOffer();

        $service = mock(SearchService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLocal')->andReturn([
            [
                'provider_name' => 'GoodProvider',
                'offers' => [$offer],
                'status' => ProviderStatus::SUCCESS,
                'duration_ms' => 5,
                'error_message' => null,
            ],
            [
                'provider_name' => 'BrokenProvider',
                'offers' => [],
                'status' => ProviderStatus::ERROR,
                'duration_ms' => 0,
                'error_message' => 'Provider request failed.',
            ],
        ]);
        $service->shouldReceive('resolveRemote')->andReturn([]);

        $result = $service->search(makeParams());

        expect($result['flights'])->toHaveCount(1);

        $goodMeta = collect($result['providerResults'])
            ->first(fn ($p) => $p['provider_name'] === 'GoodProvider');

        $brokenMeta = collect($result['providerResults'])
            ->first(fn ($p) => $p['provider_name'] === 'BrokenProvider');

        expect($goodMeta['status']->value)->toBe('success')
            ->and($brokenMeta['status']->value)->toBe('error')
            ->and($brokenMeta['error_message'])->toBe('Provider request failed.')
            ->and($brokenMeta['error_message'])->not->toContain('Connection refused');
    });
});
