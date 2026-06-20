<?php

namespace App\FlightSearch\ValueObjects;

readonly class ProviderResult
{
    public function __construct(
        public string $name,
        public string $status,
        public int $offers,
        public int $durationMs,
        public ?string $errorMessage = null,
    ) {}
}
