<?php

namespace App\FlightSearch\Contracts;

use App\FlightSearch\ValueObjects\ProviderResultSet;
use App\FlightSearch\ValueObjects\SearchRequest;

interface ProviderContract
{
    public function name(): string;

    public function search(SearchRequest $request): ProviderResultSet;

    /**
     * Internal path used by the dispatcher to fetch raw fixtures.
     * The dispatcher prepends the application base URL.
     */
    public function endpoint(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fixtures(): array;
}
