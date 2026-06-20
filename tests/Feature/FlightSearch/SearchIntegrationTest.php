<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function providerAFixtures(): array
{
    return [
        ['carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB', 'depart' => '2026-07-01T08:00:00', 'arrive' => '2026-07-01T12:30:00', 'stops' => 0, 'fare_usd' => 320.00, 'flight_no' => 'AA101'],
        ['carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB', 'depart' => '2026-07-01T22:10:00', 'arrive' => '2026-07-02T02:40:00', 'stops' => 0, 'fare_usd' => 280.00, 'flight_no' => 'AA205'],
        ['carrier' => 'BS', 'from' => 'DAC', 'to' => 'DXB', 'depart' => '2026-07-01T09:15:00', 'arrive' => '2026-07-01T15:00:00', 'stops' => 1, 'fare_usd' => 310.00, 'flight_no' => 'BS220'],
        ['carrier' => 'EK', 'from' => 'DAC', 'to' => 'DXB', 'depart' => '2026-07-01T03:45:00', 'arrive' => '2026-07-01T06:50:00', 'stops' => 0, 'fare_usd' => 410.00, 'flight_no' => 'EK585'],
    ];
}

function providerBFixtures(): array
{
    return [
        ['airline_code' => 'BS', 'origin' => 'DAC', 'destination' => 'DXB', 'departure_time' => '2026-07-01 09:15', 'arrival_time' => '2026-07-01 15:00', 'segments' => 1, 'price' => ['amount' => 295, 'currency' => 'USD'], 'number' => 'BS220'],
        ['airline_code' => 'BS', 'origin' => 'DAC', 'destination' => 'DXB', 'departure_time' => '2026-07-01 14:30', 'arrival_time' => '2026-07-01 19:20', 'segments' => 1, 'price' => ['amount' => 265, 'currency' => 'USD'], 'number' => 'BS118'],
        ['airline_code' => 'EK', 'origin' => 'DAC', 'destination' => 'DXB', 'departure_time' => '2026-07-01 03:45', 'arrival_time' => '2026-07-01 06:50', 'segments' => 0, 'price' => ['amount' => 399, 'currency' => 'USD'], 'number' => 'EK585'],
    ];
}

function providerCFixtures(): array
{
    return [
        ['iata' => 'AA', 'route' => ['src' => 'DAC', 'dst' => 'DXB'], 'times' => ['dep' => 1782892800, 'arr' => 1782909000], 'layovers' => 0, 'total_price' => 335, 'currency' => 'USD', 'code' => 'AA101'],
        ['iata' => 'CJ', 'route' => ['src' => 'DAC', 'dst' => 'DXB'], 'times' => ['dep' => 1782885600, 'arr' => 1782903600], 'layovers' => 2, 'total_price' => 270, 'currency' => 'USD', 'code' => 'CJ300'],
        ['iata' => 'EK', 'route' => ['src' => 'DAC', 'dst' => 'DXB'], 'times' => ['dep' => 1782877500, 'arr' => 1782888600], 'layovers' => 0, 'total_price' => 405, 'currency' => 'USD', 'code' => 'EK585'],
    ];
}

test('end-to-end search normalizes, deduplicates and returns provider meta', function () {
    Http::fake([
        '*api/internal/providers/ProviderA/fixtures*' => Http::response(providerAFixtures()),
        '*api/internal/providers/ProviderB/fixtures*' => Http::response(providerBFixtures()),
        '*api/internal/providers/ProviderC/fixtures*' => Http::response(providerCFixtures()),
    ]);

    $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=2');

    $response->assertOk();

    $data = $response->json('data');
    $meta = $response->json('meta');

    $flightNumbers = [];
    foreach ($data as $f) {
        $flightNumbers[] = $f['flight_number'];
    }
    $ek585Count = 0;
    foreach ($flightNumbers as $no) {
        if ($no === 'EK585') {
            $ek585Count++;
        }
    }

    expect($data)->toHaveCount(6)
        ->and($flightNumbers)->toContain('AA101', 'AA205', 'BS220', 'BS118', 'EK585', 'CJ300')
        ->and($ek585Count)->toBe(1)
        ->and($meta['total_flights'])->toBe(10)
        ->and($meta['unique_flights'])->toBe(6)
        ->and($meta['providers'])->toHaveCount(3);

    $ek585 = collect($data)->first(fn ($f) => $f['flight_number'] === 'EK585');
    expect((float) $ek585['price'])->toBe(399.0)
        ->and($ek585['provider'])->toBe('ProviderB')
        ->and((float) $ek585['total_price'])->toBe(798.0);
});

test('end-to-end search applies filters and sorting', function () {
    Http::fake([
        '*api/internal/providers/ProviderA/fixtures*' => Http::response(providerAFixtures()),
        '*api/internal/providers/ProviderB/fixtures*' => Http::response(providerBFixtures()),
        '*api/internal/providers/ProviderC/fixtures*' => Http::response(providerCFixtures()),
    ]);

    $response = $this->getJson('/api/flights/search?from=DAC&to=DXB&date=2026-07-01&passengers=1&filter[carriers]=BS&sort=price:asc');

    $response->assertOk();

    $data = $response->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['flight_number'])->toBe('BS118')
        ->and($data[1]['flight_number'])->toBe('BS220')
        ->and($data[0]['price'])->toBeLessThan($data[1]['price']);
});
