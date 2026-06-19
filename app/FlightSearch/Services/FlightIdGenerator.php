<?php

namespace App\FlightSearch\Services;

class FlightIdGenerator
{
    public function generate(
        string $carrier,
        string $flightNumber,
        string $origin,
        string $destination,
        string $departureUtc,
    ): string {
        $canonical = implode('|', [
            strtoupper($carrier),
            strtoupper($flightNumber),
            strtoupper($origin),
            strtoupper($destination),
            $departureUtc,
        ]);

        return hash('sha256', $canonical);
    }
}
