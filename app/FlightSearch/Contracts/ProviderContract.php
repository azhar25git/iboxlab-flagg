<?php

namespace App\FlightSearch\Contracts;

use App\FlightSearch\ValueObjects\FlightOffer;

interface ProviderContract
{
    public function name(): string;

    /**
     * Internal path used by the dispatcher to fetch raw fixtures.
     * The dispatcher prepends the application base URL.
     */
    public function endpoint(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fixtures(): array;

    /**
     * @param  array<string, mixed>  $raw
     */
    public function normalize(array $raw): FlightOffer;
}
