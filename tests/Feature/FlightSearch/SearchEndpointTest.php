<?php

use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Services\SearchService;
use Tests\Helpers\FlightOfferFactory;

beforeEach(function () {
    $this->offer = FlightOfferFactory::make();
    $this->offerArray = array_merge($this->offer->toArray(), [
        'total_price' => round($this->offer->price * 2, 2),
    ]);
});

function makeServiceMock(array $offers, int $passengers = 2): mixed
{
    $service = mock(SearchService::class);
    $service->shouldReceive('search')->andReturn([
        'flights' => $offers,
        'providerResults' => [
            [
                'provider_name' => 'ProviderA',
                'offers' => $offers,
                'status' => ProviderStatus::SUCCESS,
                'duration_ms' => 10,
                'error_message' => null,
            ],
        ],
        'passengers' => $passengers,
    ]);

    return $service;
}

test('returns 200 with search results', function () {
    $this->app->instance(SearchService::class, makeServiceMock([$this->offer]));

    $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'carrier', 'origin', 'destination',
                    'departure', 'arrival', 'duration_minutes',
                    'stops', 'price', 'total_price', 'currency',
                    'flight_number', 'provider',
                ],
            ],
            'meta' => [
                'providers', 'total_offers', 'unique_flights',
                'passengers', 'currency', 'price_unit',
            ],
        ]);
});

test('returns 422 for missing required params', function () {
    $this->getJson('/api/flights/search')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from', 'to', 'date', 'passengers']);
});

test('returns 422 for invalid airport codes', function () {
    $this->getJson('/api/flights/search?from=DA&to=DXB&date=2026-07-01&passengers=2')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from']);

    $this->getJson('/api/flights/search?from=DAC&to=LONDON&date=2026-07-01&passengers=2')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);

    $this->getJson('/api/flights/search?from=123&to=DXB&date=2026-07-01&passengers=2')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from']);
});

test('returns 422 for past date', function () {
    $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2020-01-01&passengers=2')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

test('returns 422 for invalid passengers', function () {
    $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['passengers']);
});

test('sorts by price ascending', function () {
    $cheap = FlightOfferFactory::make([
        'price' => 100, 'flightNumber' => 'AA101', 'carrier' => 'AA',
    ]);
    $expensive = FlightOfferFactory::make([
        'price' => 500, 'flightNumber' => 'AA205', 'carrier' => 'AA',
    ]);

    $this->app->instance(SearchService::class, makeServiceMock([$cheap, $expensive]));

    $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2&sort=price:asc');

    $response->assertOk();
    expect((float) $response->json('data.0.price'))->toBe(100.0);
});

test('sorts by price descending', function () {
    $cheap = FlightOfferFactory::make([
        'price' => 100, 'flightNumber' => 'AA101', 'carrier' => 'AA',
    ]);
    $expensive = FlightOfferFactory::make([
        'price' => 500, 'flightNumber' => 'AA205', 'carrier' => 'AA',
    ]);

    $this->app->instance(SearchService::class, makeServiceMock([$expensive, $cheap]));

    $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2&sort=price:desc');

    $response->assertOk();
    expect((float) $response->json('data.0.price'))->toBe(500.0);
});

test('returns 422 for invalid sort field', function () {
    $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2&sort=invalid:asc')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort']);
});

test('returns 422 for invalid sort direction', function () {
    $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2&sort=price:up')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort']);
});

test('duration_minutes is present and positive', function () {
    $this->app->instance(SearchService::class, makeServiceMock([$this->offer]));

    $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2');

    $response->assertOk();
    $flight = $response->json('data.0');

    expect($flight['duration_minutes'])->toBeInt()->toBeGreaterThan(0);
});

test('total_price is price multiplied by passengers', function () {
    $this->app->instance(SearchService::class, makeServiceMock([$this->offer], 3));

    $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=3');

    $response->assertOk();
    $flight = $response->json('data.0');

    expect((float) $flight['total_price'])->toBe(round($flight['price'] * 3, 2));
});

test('meta contains completeness info', function () {
    $this->app->instance(SearchService::class, makeServiceMock([$this->offer]));

    $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2');

    $response->assertOk();
    $meta = $response->json('meta');

    expect($meta['providers'])->toBeArray()->toHaveCount(1)
        ->and($meta['total_offers'])->toBe(1)
        ->and($meta['unique_flights'])->toBe(1)
        ->and($meta['passengers'])->toBe(2)
        ->and($meta['currency'])->toBe('USD')
        ->and($meta['price_unit'])->toBe('per_passenger');
});
