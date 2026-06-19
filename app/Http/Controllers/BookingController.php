<?php

namespace App\Http\Controllers;

use App\FlightSearch\Services\BookingService;
use App\Models\Booking;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flight_id' => ['required', 'string'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.name' => ['required', 'string', 'max:255'],
            'passengers.*.email' => ['required', 'email', 'max:255'],
            'passengers.*.date_of_birth' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $booking = $this->bookingService->create(
                flightId: $validated['flight_id'],
                passengers: $validated['passengers'],
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'data' => $this->formatBooking($booking),
        ], 201);
    }

    public function cancel(string $reference): JsonResponse
    {
        validator(['reference' => $reference], [
            'reference' => 'regex:/^IBX-[A-F0-9]{4}$/',
        ], ['reference.regex' => 'reference invalid'])->validate();

        try {
            $booking = $this->bookingService->cancel($reference);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        return response()->json([
            'data' => $this->formatBooking($booking),
        ]);
    }

    public function show(string $reference): JsonResponse
    {
        validator(['reference' => $reference], [
            'reference' => 'regex:/^IBX-[A-F0-9]{4}$/',
        ], ['reference.regex' => 'reference invalid'])->validate();

        $booking = $this->bookingService->findByReference($reference);

        if ($booking === null) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        return response()->json([
            'data' => $this->formatBooking($booking),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBooking(Booking $booking): array
    {
        $flight = $booking->flight_snapshot;
        $pricePerPassenger = (float) ($flight['price'] ?? 0);
        $passengerCount = count($booking->passengers);

        return [
            'reference' => $booking->reference,
            'flight_id' => $booking->flight_id,
            'flight' => $flight,
            'passengers' => $booking->passengers,
            'total_price' => round($pricePerPassenger * $passengerCount, 2),
            'currency' => $flight['currency'] ?? 'USD',
            'status' => $booking->status,
            'created_at' => $booking->created_at?->toIso8601String(),
            'updated_at' => $booking->updated_at?->toIso8601String(),
        ];
    }
}
