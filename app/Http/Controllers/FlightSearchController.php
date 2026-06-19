<?php

namespace App\Http\Controllers;

use App\FlightSearch\Enums\SortDirection;
use App\FlightSearch\Enums\SortField;
use App\FlightSearch\Services\SearchService;
use App\FlightSearch\ValueObjects\SearchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlightSearchController extends Controller
{
    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    public function search(Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'from' => ['required', 'string', 'size:3'],
            'to' => ['required', 'string', 'size:3'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'passengers' => ['required', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', function ($attribute, $value, $fail): void {
                $parts = explode(':', strtolower($value), 2);
                $field = $parts[0];
                $direction = $parts[1] ?? null;

                try {
                    SortField::fromString($field);
                } catch (\InvalidArgumentException) {
                    $fail('The sort field is invalid. Allowed: price, departure, arrival, stops, duration.');
                }

                if ($direction !== null) {
                    try {
                        SortDirection::from($direction);
                    } catch (\ValueError) {
                        $fail('The sort direction is invalid. Allowed: asc, desc.');
                    }
                }
            }],
            'filter.stops' => ['nullable', 'integer', 'min:0'],
            'filter.carrier' => ['nullable', 'string'],
            'filter.max_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sortField = null;
        $sortDirection = null;

        if (! empty($validated['sort'])) {
            $parts = explode(':', strtolower($validated['sort']), 2);
            $sortField = $parts[0];
            $sortDirection = $parts[1] ?? null;
        }

        $searchRequest = new SearchRequest(
            from: strtoupper($validated['from']),
            to: strtoupper($validated['to']),
            date: $validated['date'],
            passengers: (int) $validated['passengers'],
            sortField: $sortField,
            sortDirection: $sortDirection,
            filterStops: isset($validated['filter']['stops']) ? (int) $validated['filter']['stops'] : null,
            filterCarrier: $validated['filter']['carrier'] ?? null,
            filterMaxPrice: isset($validated['filter']['max_price']) ? (float) $validated['filter']['max_price'] : null,
        );

        $response = $this->searchService->search($searchRequest);

        return response()->json($response->toArray());
    }
}
