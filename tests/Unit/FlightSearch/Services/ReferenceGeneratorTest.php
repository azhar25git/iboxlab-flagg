<?php

use App\FlightSearch\Services\ReferenceGenerator;

test('generates reference with IBX prefix', function () {
    $generator = new ReferenceGenerator;

    $reference = $generator->generate();

    expect($reference)->toMatch(ReferenceGenerator::pattern());
});

test('generates unique references', function () {
    $generator = new ReferenceGenerator;
    $references = [];

    for ($i = 0; $i < 50; $i++) {
        $references[] = $generator->generate();
    }

    expect(count(array_unique($references)))->toBe(50);
});
