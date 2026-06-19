<?php

namespace App\Providers;

use App\FlightSearch\Adapters\ProviderA;
use App\FlightSearch\Adapters\ProviderB;
use App\FlightSearch\Adapters\ProviderC;
use App\FlightSearch\Services\ProviderRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderRegistry::class, function ($app): ProviderRegistry {
            $registry = new ProviderRegistry;

            $registry->register($app->make(ProviderA::class));
            $registry->register($app->make(ProviderB::class));
            $registry->register($app->make(ProviderC::class));

            return $registry;
        });
    }

    public function boot(): void
    {
        //
    }
}
