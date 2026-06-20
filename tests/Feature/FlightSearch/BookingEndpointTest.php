<?php

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\FlightOfferFactory;

beforeEach(function () {
    $this->offer = FlightOfferFactory::make();

    Cache::put('flight_offer:'.$this->offer->id, $this->offer->toCacheArray(), 60);
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
                'reference', 'flight_id', 'flight', 'passengers',
                'total_price', 'currency', 'status', 'created_at', 'updated_at',
            ],
        ])
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.flight.id', $this->offer->id)
        ->assertJsonPath('data.currency', 'USD');
});

test('returns 404 when flight_id not found', function () {
    $payload = [
        'flight_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
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
    $response = $this->getJson('/api/bookings/IBX-01KVG7X96SPQ44NPZJBJ222KK8');

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

test('booking response includes total_price', function () {
    $this->offer = FlightOfferFactory::make(['price' => 150.00]);

    Cache::put('flight_offer:'.$this->offer->id, $this->offer->toCacheArray(), 60);

    $response = $this->postJson('/api/bookings', [
        'flight_id' => $this->offer->id,
        'passengers' => [
            ['name' => 'A', 'email' => 'a@b.com', 'date_of_birth' => '1990-01-01'],
            ['name' => 'B', 'email' => 'b@c.com', 'date_of_birth' => '1991-02-02'],
            ['name' => 'C', 'email' => 'c@d.com', 'date_of_birth' => '1992-03-03'],
        ],
    ]);

    $response->assertCreated();
    expect((float) $response->json('data.total_price'))->toBe(450.0);
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

test('returns 422 for invalid reference pattern on show', function () {
    $response = $this->getJson('/api/bookings/NONSENSE');

    $response->assertStatus(422)
        ->assertJsonPath('message', 'reference invalid');
});

test('booking response includes updated_at', function () {
    $response = $this->postJson('/api/bookings', [
        'flight_id' => $this->offer->id,
        'passengers' => [
            ['name' => 'John', 'email' => 'john@example.com', 'date_of_birth' => '1990-01-01'],
        ],
    ]);

    $ref = $response->json('data.reference');

    $get = $this->getJson("/api/bookings/{$ref}");

    $get->assertOk()
        ->assertJsonStructure(['data' => ['updated_at']])
        ->assertJsonPath('data.updated_at', $response->json('data.updated_at'));
});
