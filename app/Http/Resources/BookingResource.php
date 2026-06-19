<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $flight */
        $flight = $this->flight_snapshot;
        /** @var array<int, array<string, mixed>> $passengers */
        $passengers = $this->passengers;
        $pricePerPassenger = (float) ($flight['price'] ?? 0);

        return [
            'reference' => $this->reference,
            'flight_id' => $this->flight_id,
            'flight' => $flight,
            'passengers' => $passengers,
            'total_price' => round($pricePerPassenger * count($passengers), 2),
            'currency' => $flight['currency'] ?? 'USD',
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
