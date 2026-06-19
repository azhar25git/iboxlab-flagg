<?php

use App\FlightSearch\Services\FlightIdGenerator;

test('generates deterministic id from same inputs', function () {
    $generator = new FlightIdGenerator;

    $id1 = $generator->generate('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');
    $id2 = $generator->generate('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');

    expect($id1)->toBe($id2);
});

test('generates different ids for different flights', function () {
    $generator = new FlightIdGenerator;

    $id1 = $generator->generate('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');
    $id2 = $generator->generate('EK', 'EK586', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');

    expect($id1)->not->toBe($id2);
});

test('id is case-insensitive for carrier and route', function () {
    $generator = new FlightIdGenerator;

    $id1 = $generator->generate('ek', 'ek585', 'dac', 'dxb', '2026-07-01T03:45:00+00:00');
    $id2 = $generator->generate('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');

    expect($id1)->toBe($id2);
});

test('id changes with different departure time', function () {
    $generator = new FlightIdGenerator;

    $id1 = $generator->generate('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');
    $id2 = $generator->generate('EK', 'EK585', 'DAC', 'DXB', '2026-07-02T03:45:00+00:00');

    expect($id1)->not->toBe($id2);
});

test('id is 64-character hex string', function () {
    $generator = new FlightIdGenerator;

    $id = $generator->generate('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');

    expect($id)->toHaveLength(64)
        ->and($id)->toMatch('/^[a-f0-9]{64}$/');
});
