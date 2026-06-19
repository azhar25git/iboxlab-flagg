<?php

namespace App\FlightSearch\Services;

use App\FlightSearch\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BookingService
{
    public function __construct(
        private readonly ReferenceGenerator $referenceGenerator,
        private readonly FlightOfferRepository $flights,
    ) {}

    /**
     * @param  array<int, array{name: string, email: string, date_of_birth: string}>  $passengers
     */
    public function create(string $flightId, array $passengers): Booking
    {
        $flight = $this->flights->find($flightId);

        if ($flight === null) {
            throw new \InvalidArgumentException('Flight not found for the given identifier.');
        }

        $passengerData = array_map(
            fn (array $p) => [
                'name' => $p['name'],
                'email' => $p['email'],
                'date_of_birth' => $p['date_of_birth'],
            ],
            $passengers,
        );

        return Booking::create([
            'reference' => $this->referenceGenerator->generate(),
            'flight_id' => $flightId,
            'flight_snapshot' => $flight->toArray(),
            'passengers' => $passengerData,
            'status' => BookingStatus::CONFIRMED->value,
        ]);
    }

    public function cancel(string $reference): Booking
    {
        $booking = $this->findOrFail($reference);

        if ($booking->status === BookingStatus::CANCELLED->value) {
            return $booking;
        }

        $booking->update(['status' => BookingStatus::CANCELLED->value]);

        return $booking->fresh();
    }

    public function findByReference(string $reference): ?Booking
    {
        return Booking::where('reference', $reference)->first();
    }

    public function findOrFail(string $reference): Booking
    {
        $booking = $this->findByReference($reference);

        if ($booking === null) {
            throw (new ModelNotFoundException)->setModel(Booking::class, [$reference]);
        }

        return $booking;
    }
}
