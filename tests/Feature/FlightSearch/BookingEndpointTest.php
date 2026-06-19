<?php

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Services\ProviderRegistry;
use App\FlightSearch\ValueObjects\ProviderResultSet;
use App\Models\Booking;
use Tests\Helpers\FlightOfferFactory;

beforeEach(function () {
    $this->offer = FlightOfferFactory::make();

    $resultSet = new ProviderResultSet(
        providerName: 'ProviderA',
        offers: [$this->offer],
        status: ProviderStatus::SUCCESS,
        durationMs: 5,
    );

    $mockProvider = mock(ProviderContract::class);
    $mockProvider->shouldReceive('name')->andReturn('ProviderA');
    $mockProvider->shouldReceive('search')->andReturn($resultSet);

    $registry = new ProviderRegistry;
    $registry->register($mockProvider);

    $this->app->instance(ProviderRegistry::class, $registry);
});

test('creates booking with valid data', function () {
    $payload = [
        'flight_id' => $this->offer->id,
        'passengers' => [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'date_of_birth' => '1990-05-15'],
        ],
    ];

    $response = $this->postJson('/api/bookings', $payload);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'reference', 'flight_id', 'flight', 'passengers', 'status', 'created_at',
            ],
        ])
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.flight.id', $this->offer->id);
});

test('returns 404 when flight_id not found', function () {
    $payload = [
        'flight_id' => 'nonexistent-id',
        'passengers' => [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'date_of_birth' => '1990-05-15'],
        ],
    ];

    $response = $this->postJson('/api/bookings', $payload);

    $response->assertNotFound();
});

test('retrieves booking by reference', function () {
    $create = $this->postJson('/api/bookings', [
        'flight_id' => $this->offer->id,
        'passengers' => [
            ['name' => 'Jane', 'email' => 'jane@example.com', 'date_of_birth' => '1985-01-01'],
        ],
    ]);

    $reference = $create->json('data.reference');

    $response = $this->getJson("/api/bookings/{$reference}");

    $response->assertOk()
        ->assertJsonPath('data.reference', $reference)
        ->assertJsonPath('data.passengers.0.name', 'Jane');
});

test('returns 404 for non-existent reference', function () {
    $response = $this->getJson('/api/bookings/NOTFOUND');

    $response->assertNotFound();
});

test('returns 422 for missing passenger fields', function () {
    $response = $this->postJson('/api/bookings', [
        'flight_id' => $this->offer->id,
        'passengers' => [
            ['name' => 'John'],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['passengers.0.email', 'passengers.0.date_of_birth']);
});

test('returns 422 for empty passengers array', function () {
    $response = $this->postJson('/api/bookings', [
        'flight_id' => $this->offer->id,
        'passengers' => [],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['passengers']);
});

test('returns 422 for missing flight_id', function () {
    $response = $this->postJson('/api/bookings', [
        'passengers' => [
            ['name' => 'John', 'email' => 'john@example.com', 'date_of_birth' => '1990-01-01'],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['flight_id']);
});

test('booking stores immutable flight snapshot', function () {
    $this->postJson('/api/bookings', [
        'flight_id' => $this->offer->id,
        'passengers' => [
            ['name' => 'John', 'email' => 'john@example.com', 'date_of_birth' => '1990-01-01'],
        ],
    ]);

    $booking = Booking::first();

    expect($booking->flight_snapshot)->toBeArray()
        ->and($booking->flight_snapshot['id'])->toBe($this->offer->id);
});
