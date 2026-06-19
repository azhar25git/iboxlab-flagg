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
        public int $passengers = 1,
    ) {}

    /**
     * @return array<string, mixed>
     */
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
                fn (FlightOffer $f) => array_merge($f->toArray(), [
                    'total_price' => round($f->price * $this->passengers, 2),
                ]),
                $this->flights,
            ),
            'meta' => [
                'providers' => $providers,
                'total_offers' => $totalOffers,
                'unique_flights' => count($this->flights),
                'passengers' => $this->passengers,
                'currency' => 'USD',
                'price_unit' => 'per_passenger',
            ],
        ];
    }
}
