<?php

namespace App\Http\Resources;

use App\FlightSearch\ValueObjects\FlightOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FlightOffer */
class FlightOfferResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        private readonly int $passengers = 1,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'carrier' => $this->carrier,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'departure' => $this->departure,
            'arrival' => $this->arrival,
            'duration_minutes' => $this->durationMinutes(),
            'stops' => $this->stops,
            'price' => $this->price,
            'total_price' => round($this->price * $this->passengers, 2),
            'currency' => $this->currency,
            'flight_number' => $this->flightNumber,
            'provider' => $this->provider,
        ];
    }
}
