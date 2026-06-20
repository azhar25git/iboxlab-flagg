<?php

namespace App\Http\Controllers;

use App\FlightSearch\Enums\SortDirection;
use App\FlightSearch\Enums\SortField;
use App\FlightSearch\Services\SearchService;
use App\FlightSearch\ValueObjects\ProviderResult;
use App\FlightSearch\ValueObjects\SearchParams;
use App\Http\Resources\FlightOfferResource;
use App\Http\Resources\ProviderMetaResource;
use App\Http\Resources\SearchMetaResource;
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
            'filter.max_stops' => ['nullable', 'integer', 'min:0'],
            'filter.carriers' => ['nullable', 'string'],
            'filter.max_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sortField = null;
        $sortDirection = null;

        if (! empty($validated['sort'])) {
            $parts = explode(':', strtolower($validated['sort']), 2);
            $sortField = $parts[0];
            $sortDirection = $parts[1] ?? null;
        }

        $params = new SearchParams(
            from: strtoupper($validated['from']),
            to: strtoupper($validated['to']),
            date: $validated['date'],
            passengers: (int) $validated['passengers'],
            sortField: $sortField,
            sortDirection: $sortDirection,
            filterMaxStops: isset($validated['filter']['max_stops']) ? (int) $validated['filter']['max_stops'] : null,
            filterCarriers: ! empty($validated['filter']['carriers'])
                ? array_map('strtoupper', array_map('trim', explode(',', $validated['filter']['carriers'])))
                : null,
            filterMaxPrice: isset($validated['filter']['max_price']) ? (float) $validated['filter']['max_price'] : null,
        );

        $result = $this->searchService->search($params);

        $data = [];
        foreach ($result['flights'] as $f) {
            $data[] = new FlightOfferResource($f, $result['passengers']);
        }

        $providerCounts = [];
        foreach ($result['flights'] as $f) {
            $providerCounts[$f->provider] = ($providerCounts[$f->provider] ?? 0) + 1;
        }

        $totalRaw = 0;
        $providers = [];
        foreach ($result['providerResults'] as $r) {
            $totalRaw += count($r['offers']);
            $providers[] = new ProviderMetaResource(new ProviderResult(
                name: $r['provider_name'],
                status: $r['status']->value,
                offers: $providerCounts[$r['provider_name']] ?? 0,
                durationMs: $r['duration_ms'],
                errorMessage: $r['error_message'],
            ));
        }

        return response()->json([
            'data' => $data,
            'meta' => new SearchMetaResource([
                'providers' => $providers,
                'total_flights' => $totalRaw,
                'unique_flights' => count($result['flights']),
                'passengers' => $result['passengers'],
                'currency' => 'USD',
                'price_unit' => 'per_passenger',
            ]),
        ]);
    }
}
