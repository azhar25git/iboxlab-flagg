<?php

namespace App\FlightSearch\ValueObjects;

readonly class Passenger
{
    public function __construct(
        public string $name,
        public string $email,
        public string $dateOfBirth,
    ) {}
}
