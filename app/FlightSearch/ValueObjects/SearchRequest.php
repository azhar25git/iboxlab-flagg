<?php

namespace App\FlightSearch\ValueObjects;

readonly class SearchRequest
{
    public function __construct(
        public string $from,
        public string $to,
        public string $date,
        public int $passengers,
        public ?string $sortField = null,
        public ?string $sortDirection = null,
        public ?int $filterStops = null,
        public ?string $filterCarrier = null,
        public ?float $filterMaxPrice = null,
    ) {}
}
