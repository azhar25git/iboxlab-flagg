<?php

use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Services\ProviderDispatcher;
use App\FlightSearch\ValueObjects\ProviderResultSet;
use Tests\Helpers\FlightOfferFactory;

beforeEach(function () {
    $this->offer = FlightOfferFactory::make();

    $dispatcher = mock(ProviderDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->andReturn([
        new ProviderResultSet('ProviderA', [$this->offer], ProviderStatus::SUCCESS, durationMs: 10),
    ]);

    $this->app->instance(ProviderDispatcher::class, $dispatcher);
});

test('returns 200 with search results', function () {
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

    $dispatcher = mock(ProviderDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->andReturn([
        new ProviderResultSet('ProviderA', [$expensive, $cheap], ProviderStatus::SUCCESS, durationMs: 10),
    ]);
    $this->app->instance(ProviderDispatcher::class, $dispatcher);

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

    $dispatcher = mock(ProviderDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->andReturn([
        new ProviderResultSet('ProviderA', [$expensive, $cheap], ProviderStatus::SUCCESS, durationMs: 10),
    ]);
    $this->app->instance(ProviderDispatcher::class, $dispatcher);

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
    $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2');

    $response->assertOk();
    $flight = $response->json('data.0');

    expect($flight['duration_minutes'])->toBeInt()->toBeGreaterThan(0);
});

test('total_price is price multiplied by passengers', function () {
    $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=3');

    $response->assertOk();
    $flight = $response->json('data.0');

    expect((float) $flight['total_price'])->toBe(round($flight['price'] * 3, 2));
});

test('meta contains completeness info', function () {
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
