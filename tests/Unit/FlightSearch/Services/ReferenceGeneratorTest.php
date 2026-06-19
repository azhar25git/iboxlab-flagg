<?php

use App\Models\Booking;

test('generates reference with IBX prefix', function () {
    $reference = Booking::generateReference();

    expect($reference)->toMatch(Booking::referencePattern());
});

test('generates unique references', function () {
    $references = [];

    for ($i = 0; $i < 50; $i++) {
        $references[] = Booking::generateReference();
    }

    expect(count(array_unique($references)))->toBe(50);
});
