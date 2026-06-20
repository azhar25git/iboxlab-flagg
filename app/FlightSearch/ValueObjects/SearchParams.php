<?php

namespace App\FlightSearch\ValueObjects;

readonly class SearchParams
{
    /**
     * @param  string[]|null  $filterCarriers
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $date,
        public int $passengers,
        public ?string $sortField = null,
        public ?string $sortDirection = null,
        public ?int $filterMaxStops = null,
        public ?array $filterCarriers = null,
        public ?float $filterMaxPrice = null,
    ) {}
}
