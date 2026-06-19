<?php

namespace App\FlightSearch\Contracts;

use App\FlightSearch\ValueObjects\ProviderResultSet;
use App\FlightSearch\ValueObjects\SearchRequest;

interface ProviderContract
{
    public function name(): string;

    public function search(SearchRequest $request): ProviderResultSet;
}
