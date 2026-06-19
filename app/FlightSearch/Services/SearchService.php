<?php

namespace App\FlightSearch\Services;

use App\FlightSearch\Enums\SortDirection;
use App\FlightSearch\Enums\SortField;
use App\FlightSearch\ValueObjects\FlightOffer;
use App\FlightSearch\ValueObjects\SearchRequest;
use App\FlightSearch\ValueObjects\SearchResponse;

class SearchService
{
    public function __construct(
        private readonly ProviderDispatcher $dispatcher,
        private readonly FlightOfferRepository $flights,
    ) {}

    public function search(SearchRequest $request): SearchResponse
    {
        $providerResults = $this->dispatcher->dispatch($request);

        $allOffers = [];
        foreach ($providerResults as $result) {
            foreach ($result->offers as $offer) {
                $allOffers[] = $offer;
            }
        }

        $unique = $this->deduplicate($allOffers);

        foreach ($unique as $offer) {
            $this->flights->remember($offer);
        }

        $unique = $this->filter($unique, $request);

        $unique = $this->sort($unique, $request);

        return new SearchResponse(
            flights: $unique,
            providerResults: $providerResults,
            passengers: $request->passengers,
        );
    }

    /**
     * @param  FlightOffer[]  $offers
     * @return FlightOffer[]
     */
    private function deduplicate(array $offers): array
    {
        $seen = [];

        foreach ($offers as $offer) {
            if (! isset($seen[$offer->id]) || $offer->price < $seen[$offer->id]->price) {
                $seen[$offer->id] = $offer;
            }
        }

        return array_values($seen);
    }

    /**
     * @param  FlightOffer[]  $offers
     * @return FlightOffer[]
     */
    private function filter(array $offers, SearchRequest $request): array
    {
        return array_values(array_filter($offers, function (FlightOffer $offer) use ($request): bool {
            if ($request->filterStops !== null && $offer->stops > $request->filterStops) {
                return false;
            }

            if ($request->filterCarrier !== null && strtoupper($offer->carrier) !== strtoupper($request->filterCarrier)) {
                return false;
            }

            if ($request->filterMaxPrice !== null && $offer->price > $request->filterMaxPrice) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  FlightOffer[]  $offers
     * @return FlightOffer[]
     */
    private function sort(array $offers, SearchRequest $request): array
    {
        $field = $request->sortField ? SortField::fromString($request->sortField) : SortField::PRICE;
        $direction = $request->sortDirection ? SortDirection::from($request->sortDirection) : SortDirection::ASC;

        usort($offers, function (FlightOffer $a, FlightOffer $b) use ($field, $direction): int {
            $valueA = match ($field) {
                SortField::PRICE => $a->price,
                SortField::DEPARTURE => $a->departure,
                SortField::ARRIVAL => $a->arrival,
                SortField::STOPS => $a->stops,
                SortField::DURATION => $a->durationMinutes(),
            };

            $valueB = match ($field) {
                SortField::PRICE => $b->price,
                SortField::DEPARTURE => $b->departure,
                SortField::ARRIVAL => $b->arrival,
                SortField::STOPS => $b->stops,
                SortField::DURATION => $b->durationMinutes(),
            };

            $result = $valueA <=> $valueB;

            return $direction === SortDirection::ASC ? $result : -$result;
        });

        return $offers;
    }
}
