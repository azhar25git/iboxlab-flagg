<?php

namespace App\FlightSearch\ValueObjects;

readonly class SearchResponse
{
    /**
     * @param  FlightOffer[]  $flights
     * @param  ProviderResultSet[]  $providerResults
     */
    public function __construct(
        public array $flights,
        public array $providerResults,
    ) {}

    public function toArray(): array
    {
        $providers = array_map(
            fn (ProviderResultSet $r) => $r->toMetaArray(),
            $this->providerResults,
        );

        $totalOffers = array_sum(
            array_map(fn (array $p) => $p['offers'], $providers),
        );

        return [
            'data' => array_map(
                fn (FlightOffer $f) => $f->toArray(),
                $this->flights,
            ),
            'meta' => [
                'providers' => $providers,
                'total_offers' => $totalOffers,
                'unique_flights' => count($this->flights),
            ],
        ];
    }
}
