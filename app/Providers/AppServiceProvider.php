<?php

namespace App\Providers;

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Services\SearchService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('providers', function ($app): array {
            $providers = [];

            foreach ((array) config('providers.providers', []) as $class) {
                /** @var ProviderContract $instance */
                $instance = $app->make($class);
                $providers[$instance->name()] = $instance;
            }

            return $providers;
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
