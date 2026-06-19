<?php

namespace App\FlightSearch\Services;

use App\FlightSearch\Enums\BookingStatus;
use App\FlightSearch\ValueObjects\FlightOffer;
use App\FlightSearch\ValueObjects\SearchRequest;
use App\Models\Booking;
use App\Models\Booking as BookingModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BookingService
{
    public function __construct(
        private readonly ReferenceGenerator $referenceGenerator,
        private readonly ProviderRegistry $registry,
    ) {}

    /**
     * @param  array<int, array{name: string, email: string, date_of_birth: string}>  $passengers
     */
    public function create(string $flightId, array $passengers): Booking
    {
        $flight = $this->resolveFlight($flightId);

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

        return BookingModel::create([
            'reference' => $this->referenceGenerator->generate(),
            'flight_id' => $flightId,
            'flight_snapshot' => $flight->toArray(),
            'passengers' => $passengerData,
            'status' => 'confirmed',
        ]);
    }

    public function cancel(string $reference): Booking
    {
        $booking = $this->findOrFail($reference);

        if ($booking->status === BookingStatus::CANCELLED->value) {
            throw new \InvalidArgumentException('Booking is already cancelled.');
        }

        $booking->update(['status' => BookingStatus::CANCELLED->value]);

        return $booking->fresh();
    }

    public function findByReference(string $reference): ?Booking
    {
        return BookingModel::where('reference', $reference)->first();
    }

    public function findOrFail(string $reference): Booking
    {
        $booking = $this->findByReference($reference);

        if ($booking === null) {
            throw (new ModelNotFoundException)->setModel(BookingModel::class, [$reference]);
        }

        return $booking;
    }

    private function resolveFlight(string $flightId): ?FlightOffer
    {
        foreach ($this->registry->all() as $provider) {
            $result = $provider->search($this->dummyRequest());

            foreach ($result->offers as $offer) {
                if ($offer->id === $flightId) {
                    return $offer;
                }
            }
        }

        return null;
    }

    private function dummyRequest(): SearchRequest
    {
        return new SearchRequest(
            from: 'DAC',
            to: 'DXB',
            date: '2026-07-01',
            passengers: 1,
        );
    }
}
