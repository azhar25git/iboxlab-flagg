<?php

namespace App\Providers;

use App\FlightSearch\Adapters\ProviderA;
use App\FlightSearch\Adapters\ProviderB;
use App\FlightSearch\Adapters\ProviderC;
use App\FlightSearch\Services\SearchService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('providers', function ($app): array {
            return [
                'ProviderA' => $app->make(ProviderA::class),
                'ProviderB' => $app->make(ProviderB::class),
                'ProviderC' => $app->make(ProviderC::class),
            ];
        });

        $this->app->bind(SearchService::class, function ($app): SearchService {
            return new SearchService($app->make('providers'));
        });
    }

    public function boot(): void
    {
        //
    }
}
