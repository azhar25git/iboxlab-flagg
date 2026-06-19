<?php

namespace App\Http\Controllers;

use App\FlightSearch\Enums\BookingStatus;
use App\FlightSearch\ValueObjects\FlightOffer;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BookingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flight_id' => ['required', 'string'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.name' => ['required', 'string', 'max:255'],
            'passengers.*.email' => ['required', 'email', 'max:255'],
            'passengers.*.date_of_birth' => ['required', 'date_format:Y-m-d'],
        ]);

        $data = Cache::get('flight_offer:'.$validated['flight_id']);

        if (! is_array($data)) {
            return response()->json(['message' => 'Flight not found for the given identifier.'], 404);
        }

        $flight = FlightOffer::fromArray($data);

        $passengers = [];
        foreach ($validated['passengers'] as $p) {
            $passengers[] = [
                'name' => $p['name'],
                'email' => $p['email'],
                'date_of_birth' => $p['date_of_birth'],
            ];
        }

        $booking = Booking::create([
            'reference' => Booking::generateReference(),
            'flight_id' => $validated['flight_id'],
            'flight_snapshot' => $flight->toArray(),
            'passengers' => $passengers,
            'status' => BookingStatus::CONFIRMED->value,
        ]);

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    public function show(string $reference): BookingResource|JsonResponse
    {
        validator(['reference' => $reference], [
            'reference' => 'regex:'.Booking::referencePattern(),
        ], ['reference.regex' => 'reference invalid'])->validate();

        $booking = Booking::where('reference', $reference)->first();

        if ($booking === null) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        return new BookingResource($booking);
    }
}
