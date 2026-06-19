<?php

use App\FlightSearch\Services\FlightIdGenerator;
use App\FlightSearch\Services\FlightNormalizer;

beforeEach(function () {
    $this->normalizer = new FlightNormalizer(new FlightIdGenerator);
});

describe('ProviderA', function () {
    test('normalizes all fields correctly', function () {
        $raw = [
            'carrier' => 'AA',
            'from' => 'DAC',
            'to' => 'DXB',
            'depart' => '2026-07-01T08:00:00',
            'arrive' => '2026-07-01T12:30:00',
            'stops' => 0,
            'fare_usd' => 320.00,
            'flight_no' => 'AA101',
        ];

        $offer = $this->normalizer->normalizeProviderA($raw);

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

    test('generates stable id', function () {
        $raw = [
            'carrier' => 'AA',
            'from' => 'DAC',
            'to' => 'DXB',
            'depart' => '2026-07-01T08:00:00',
            'arrive' => '2026-07-01T12:30:00',
            'stops' => 0,
            'fare_usd' => 320.00,
            'flight_no' => 'AA101',
        ];

        $offer1 = $this->normalizer->normalizeProviderA($raw);
        $offer2 = $this->normalizer->normalizeProviderA($raw);

        expect($offer1->id)->toBe($offer2->id);
    });
});

describe('ProviderB', function () {
    test('normalizes fields and treats time as UTC', function () {
        $raw = [
            'airline_code' => 'BS',
            'origin' => 'DAC',
            'destination' => 'DXB',
            'departure_time' => '2026-07-01 09:15',
            'arrival_time' => '2026-07-01 15:00',
            'segments' => 1,
            'price' => ['amount' => 295, 'currency' => 'USD'],
            'number' => 'BS220',
        ];

        $offer = $this->normalizer->normalizeProviderB($raw);

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
        $raw = [
            'iata' => 'EK',
            'route' => ['src' => 'DAC', 'dst' => 'DXB'],
            'times' => ['dep' => 1782877500, 'arr' => 1782888600],
            'layovers' => 0,
            'total_price' => 405,
            'currency' => 'USD',
            'code' => 'EK585',
        ];

        $offer = $this->normalizer->normalizeProviderC($raw);

        expect($offer->departure)->toBe('2026-07-01T03:45:00+00:00')
            ->and($offer->arrival)->toBe('2026-07-01T06:50:00+00:00')
            ->and($offer->carrier)->toBe('EK')
            ->and($offer->stops)->toBe(0)
            ->and($offer->price)->toBe(405.0)
            ->and($offer->flightNumber)->toBe('EK585')
            ->and($offer->provider)->toBe('ProviderC');
    });
});

describe('cross-provider identity', function () {
    test('same flight from different providers produces identical id', function () {
        $rawA = [
            'carrier' => 'EK', 'from' => 'DAC', 'to' => 'DXB',
            'depart' => '2026-07-01T03:45:00', 'arrive' => '2026-07-01T06:50:00',
            'stops' => 0, 'fare_usd' => 410.00, 'flight_no' => 'EK585',
        ];

        $rawB = [
            'airline_code' => 'EK', 'origin' => 'DAC', 'destination' => 'DXB',
            'departure_time' => '2026-07-01 03:45', 'arrival_time' => '2026-07-01 06:50',
            'segments' => 0, 'price' => ['amount' => 399, 'currency' => 'USD'], 'number' => 'EK585',
        ];

        $rawC = [
            'iata' => 'EK', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
            'times' => ['dep' => 1782877500, 'arr' => 1782888600],
            'layovers' => 0, 'total_price' => 405, 'currency' => 'USD', 'code' => 'EK585',
        ];

        $offerA = $this->normalizer->normalizeProviderA($rawA);
        $offerB = $this->normalizer->normalizeProviderB($rawB);
        $offerC = $this->normalizer->normalizeProviderC($rawC);

        expect($offerA->id)->toBe($offerB->id)
            ->and($offerA->id)->toBe($offerC->id);
    });
});
