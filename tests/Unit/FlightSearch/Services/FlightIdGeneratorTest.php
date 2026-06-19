<?php

use App\FlightSearch\ValueObjects\FlightOffer;

test('generates deterministic id from same inputs', function () {
    $id1 = FlightOffer::makeId('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');
    $id2 = FlightOffer::makeId('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');

    expect($id1)->toBe($id2);
});

test('different flight number produces different id', function () {
    $id1 = FlightOffer::makeId('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');
    $id2 = FlightOffer::makeId('EK', 'EK586', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');

    expect($id1)->not->toBe($id2);
});

test('different carrier produces different id', function () {
    $id1 = FlightOffer::makeId('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');
    $id2 = FlightOffer::makeId('BS', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');

    expect($id1)->not->toBe($id2);
});

test('different departure produces different id', function () {
    $id1 = FlightOffer::makeId('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');
    $id2 = FlightOffer::makeId('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T08:00:00+00:00');

    expect($id1)->not->toBe($id2);
});

test('different route produces different id', function () {
    $id1 = FlightOffer::makeId('EK', 'EK585', 'DAC', 'DXB', '2026-07-01T03:45:00+00:00');
    $id2 = FlightOffer::makeId('EK', 'EK585', 'DAC', 'JED', '2026-07-01T03:45:00+00:00');

    expect($id1)->not->toBe($id2);
});
