<?php

use App\FlightSearch\Adapters\ProviderA;
use App\FlightSearch\Adapters\ProviderB;
use App\FlightSearch\Adapters\ProviderC;

return [

    /*
    |--------------------------------------------------------------------------
    | Flight Search Providers
    |--------------------------------------------------------------------------
    |
    | Registered provider classes and per-provider timeout in seconds.
    | Providers are resolved from the container via their FQCN.
    |
    */

    'providers' => [
        ProviderA::class,
        ProviderB::class,
        ProviderC::class,
    ],

    'timeout' => 5,

];
