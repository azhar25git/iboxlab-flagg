<?php

use App\FlightSearch\Adapters\ProviderA;
use App\FlightSearch\Adapters\ProviderB;
use App\FlightSearch\Adapters\ProviderC;
use App\FlightSearch\Services\FlightIdGenerator;

beforeEach(function () {
    $this->idGenerator = new FlightIdGenerator;
});

describe('ProviderA', function () {
    test('normalizes all fields correctly', function () {
        $adapter = new ProviderA($this->idGenerator);

        $offer = $adapter->normalize([
            'carrier' => 'AA',
            'from' => 'DAC',
            'to' => 'DXB',
            'depart' => '2026-07-01T08:00:00',
            'arrive' => '2026-07-01T12:30:00',
            'stops' => 0,
            'fare_usd' => 320.00,
            'flight_no' => 'AA101',
        ]);

        expect($offer->carrier)->toBe('AA')
            ->and($offer->origin)->toBe('DAC')
            ->and($offer->destination)->toBe('DXB')
            ->and($offer->departure)->toBe('2026-07-01T08:00:00+00:00')
            ->and($offer->arrival)->toBe('2026-07-01T12:30:00+00:00')
            ->and($offer->stops)->toBe(0)
            ->and($offer->price)->toBe(320.00)
            ->and($offer->currency)->toBe('USD')
            ->and($offer->flightNumber)->toBe('AA101')
            ->and($offer->provider)->toBe('ProviderA');
    });

    test('generates a stable id', function () {
        $adapter = new ProviderA($this->idGenerator);
        $raw = [
            'carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB',
            'depart' => '2026-07-01T08:00:00', 'arrive' => '2026-07-01T12:30:00',
            'stops' => 0, 'fare_usd' => 320.00, 'flight_no' => 'AA101',
        ];

        expect($adapter->normalize($raw)->id)->toBe($adapter->normalize($raw)->id);
    });
});

describe('ProviderB', function () {
    test('normalizes fields and treats time as UTC', function () {
        $adapter = new ProviderB($this->idGenerator);

        $offer = $adapter->normalize([
            'airline_code' => 'BS',
            'origin' => 'DAC',
            'destination' => 'DXB',
            'departure_time' => '2026-07-01 09:15',
            'arrival_time' => '2026-07-01 15:00',
            'segments' => 1,
            'price' => ['amount' => 295, 'currency' => 'USD'],
            'number' => 'BS220',
        ]);

        expect($offer->carrier)->toBe('BS')
            ->and($offer->departure)->toBe('2026-07-01T09:15:00+00:00')
            ->and($offer->arrival)->toBe('2026-07-01T15:00:00+00:00')
            ->and($offer->stops)->toBe(1)
            ->and($offer->price)->toBe(295.0)
            ->and($offer->currency)->toBe('USD')
            ->and($offer->flightNumber)->toBe('BS220')
            ->and($offer->provider)->toBe('ProviderB');
    });
});

describe('ProviderC', function () {
    test('normalizes unix timestamps to ISO-8601 UTC', function () {
        $adapter = new ProviderC($this->idGenerator);

        $offer = $adapter->normalize([
            'iata' => 'EK',
            'route' => ['src' => 'DAC', 'dst' => 'DXB'],
            'times' => ['dep' => 1782877500, 'arr' => 1782888600],
            'layovers' => 0,
            'total_price' => 405,
            'currency' => 'USD',
            'code' => 'EK585',
        ]);

        expect($offer->departure)->toBe('2026-07-01T03:45:00+00:00')
            ->and($offer->arrival)->toBe('2026-07-01T06:50:00+00:00')
            ->and($offer->carrier)->toBe('EK')
            ->and($offer->stops)->toBe(0)
            ->and($offer->price)->toBe(405.0)
            ->and($offer->flightNumber)->toBe('EK585')
            ->and($offer->provider)->toBe('ProviderC');
    });
});

test('same flight from different providers produces identical id', function () {
    $a = new ProviderA($this->idGenerator);
    $b = new ProviderB($this->idGenerator);
    $c = new ProviderC($this->idGenerator);

    $offerA = $a->normalize([
        'carrier' => 'EK', 'from' => 'DAC', 'to' => 'DXB',
        'depart' => '2026-07-01T03:45:00', 'arrive' => '2026-07-01T06:50:00',
        'stops' => 0, 'fare_usd' => 410.00, 'flight_no' => 'EK585',
    ]);

    $offerB = $b->normalize([
        'airline_code' => 'EK', 'origin' => 'DAC', 'destination' => 'DXB',
        'departure_time' => '2026-07-01 03:45', 'arrival_time' => '2026-07-01 06:50',
        'segments' => 0, 'price' => ['amount' => 399, 'currency' => 'USD'], 'number' => 'EK585',
    ]);

    $offerC = $c->normalize([
        'iata' => 'EK', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
        'times' => ['dep' => 1782877500, 'arr' => 1782888600],
        'layovers' => 0, 'total_price' => 405, 'currency' => 'USD', 'code' => 'EK585',
    ]);

    expect($offerA->id)->toBe($offerB->id)
        ->and($offerA->id)->toBe($offerC->id);
});
