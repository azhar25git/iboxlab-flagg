<?php

use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Services\ProviderDispatcher;
use App\FlightSearch\ValueObjects\SearchRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function dispatcher(): ProviderDispatcher
{
    return app(ProviderDispatcher::class);
}

function fixtureA(): array
{
    return [
        [
            'carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB',
            'depart' => '2026-07-01T08:00:00', 'arrive' => '2026-07-01T12:30:00',
            'stops' => 0, 'fare_usd' => 320.00, 'flight_no' => 'AA101',
        ],
    ];
}

function fixtureB(): array
{
    return [
        [
            'airline_code' => 'EK', 'origin' => 'DAC', 'destination' => 'DXB',
            'departure_time' => '2026-07-01 03:45', 'arrival_time' => '2026-07-01 06:50',
            'segments' => 0, 'price' => ['amount' => 399, 'currency' => 'USD'], 'number' => 'EK585',
        ],
    ];
}

function fixtureC(): array
{
    return [
        [
            'iata' => 'CJ', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
            'times' => ['dep' => 1782885600, 'arr' => 1782903600],
            'layovers' => 2, 'total_price' => 270, 'currency' => 'USD', 'code' => 'CJ300',
        ],
    ];
}

test('dispatches all providers concurrently and normalizes offers', function () {
    Http::fake([
        'http://localhost/api/internal/providers/ProviderA/fixtures' => Http::response(fixtureA()),
        'http://localhost/api/internal/providers/ProviderB/fixtures' => Http::response(fixtureB()),
        'http://localhost/api/internal/providers/ProviderC/fixtures' => Http::response(fixtureC()),
    ]);

    $results = dispatcher()->dispatch(new SearchRequest(
        from: 'DAC',
        to: 'DXB',
        date: '2026-07-01',
        passengers: 1,
    ));

    expect($results)->toHaveCount(3);

    $names = array_map(fn ($r) => $r->providerName, $results);
    expect($names)->toContain('ProviderA')
        ->and($names)->toContain('ProviderB')
        ->and($names)->toContain('ProviderC');

    $successResults = array_filter($results, fn ($r) => $r->status === ProviderStatus::SUCCESS);
    expect($successResults)->toHaveCount(3);
});

test('marks a provider as error on non-successful http response', function () {
    Http::fake([
        'http://localhost/api/internal/providers/ProviderA/fixtures' => Http::response(fixtureA()),
        'http://localhost/api/internal/providers/ProviderB/fixtures' => Http::response('Internal Server Error', 500),
        'http://localhost/api/internal/providers/ProviderC/fixtures' => Http::response(fixtureC()),
    ]);

    $results = dispatcher()->dispatch(new SearchRequest(
        from: 'DAC',
        to: 'DXB',
        date: '2026-07-01',
        passengers: 1,
    ));

    $byName = collect($results)->keyBy(fn ($r) => $r->providerName);

    expect($byName['ProviderA']->status)->toBe(ProviderStatus::SUCCESS)
        ->and($byName['ProviderB']->status)->toBe(ProviderStatus::ERROR)
        ->and($byName['ProviderC']->status)->toBe(ProviderStatus::SUCCESS)
        ->and($byName['ProviderB']->errorMessage)->toBe('Provider request failed.');
});
