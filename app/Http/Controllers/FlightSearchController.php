<?php

namespace App\Http\Controllers;

use App\FlightSearch\Enums\SortDirection;
use App\FlightSearch\Enums\SortField;
use App\FlightSearch\Services\SearchService;
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
            'from' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'to' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
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

        $params = [
            'from' => strtoupper($validated['from']),
            'to' => strtoupper($validated['to']),
            'date' => $validated['date'],
            'passengers' => (int) $validated['passengers'],
            'sortField' => $sortField,
            'sortDirection' => $sortDirection,
            'filterStops' => isset($validated['filter']['stops']) ? (int) $validated['filter']['stops'] : null,
            'filterCarrier' => $validated['filter']['carrier'] ?? null,
            'filterMaxPrice' => isset($validated['filter']['max_price']) ? (float) $validated['filter']['max_price'] : null,
        ];

        $result = $this->searchService->search($params);

        $data = [];
        foreach ($result['flights'] as $f) {
            $data[] = array_merge($f->toArray(), [
                'total_price' => round($f->price * $result['passengers'], 2),
            ]);
        }

        $providerCounts = [];
        foreach ($result['flights'] as $f) {
            $providerCounts[$f->provider] = ($providerCounts[$f->provider] ?? 0) + 1;
        }

        $providers = [];
        foreach ($result['providerResults'] as $r) {
            $meta = [
                'name' => $r['provider_name'],
                'status' => $r['status']->value,
                'offers' => $providerCounts[$r['provider_name']] ?? 0,
                'duration_ms' => $r['duration_ms'],
            ];
            if ($r['error_message'] !== null) {
                $meta['error_message'] = $r['error_message'];
            }
            $providers[] = $meta;
        }

        $response = [
            'data' => $data,
            'meta' => [
                'providers' => $providers,
                'total_offers' => count($data),
                'unique_flights' => count($data),
                'passengers' => $result['passengers'],
                'currency' => 'USD', // for simplicity, we assume all providers return prices in USD
                'price_unit' => 'per_passenger',
            ],
        ];

        return response()->json($response);
    }
}
