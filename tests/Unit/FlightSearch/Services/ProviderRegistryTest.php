<?php

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Services\ProviderRegistry;

test('registers and retrieves providers', function () {
    $registry = new ProviderRegistry;

    $providerA = mock(ProviderContract::class);
    $providerA->shouldReceive('name')->andReturn('ProviderA');

    $providerB = mock(ProviderContract::class);
    $providerB->shouldReceive('name')->andReturn('ProviderB');

    $registry->register($providerA);
    $registry->register($providerB);

    expect($registry->names())->toBe(['ProviderA', 'ProviderB'])
        ->and(count($registry->all()))->toBe(2);
});

test('names returns registered provider names', function () {
    $registry = new ProviderRegistry;

    $provider = mock(ProviderContract::class);
    $provider->shouldReceive('name')->andReturn('TestProvider');

    $registry->register($provider);

    expect($registry->names())->toContain('TestProvider');
});
