<?php

namespace App\FlightSearch\Services;

use App\FlightSearch\Contracts\ProviderContract;

class ProviderRegistry
{
    /**
     * @var array<string, ProviderContract>
     */
    private array $providers = [];

    public function register(ProviderContract $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    /**
     * @return array<string, ProviderContract>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->providers);
    }
}
