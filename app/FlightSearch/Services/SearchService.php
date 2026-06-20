<?php

namespace App\FlightSearch\Services;

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Enums\SortDirection;
use App\FlightSearch\Enums\SortField;
use App\FlightSearch\ValueObjects\FlightOffer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SearchService
{
    /**
     * @param  array<string, ProviderContract>  $providers
     */
    public function __construct(
        private readonly array $providers,
    ) {}

    /**
     * @param  array{from: string, to: string, date: string, passengers: int, sortField?: ?string, sortDirection?: ?string, filterMaxStops?: ?int, filterCarriers?: string[], filterMaxPrice?: ?float}  $params
     * @return array{flights: FlightOffer[], providerResults: array<int, array{provider_name: string, offers: FlightOffer[], status: ProviderStatus, error_message: ?string, duration_ms: int}>, passengers: int}
     */
    public function search(array $params): array
    {
        $providerResults = array_merge(
            $this->resolveLocal(),
            $this->resolveRemote($params),
        );

        $allOffers = [];
        foreach ($providerResults as $result) {
            foreach ($result['offers'] as $offer) {
                $allOffers[] = $offer;
            }
        }

        $unique = $this->deduplicate($allOffers);

        foreach ($unique as $offer) {
            Cache::put('flight_offer:'.$offer->id, $offer->toCacheArray(), 60);
        }

        $unique = $this->filter($unique, $params);

        $unique = $this->sort($unique, $params);

        return [
            'flights' => $unique,
            'providerResults' => $providerResults,
            'passengers' => $params['passengers'],
        ];
    }

    /**
     * @return array<int, array{provider_name: string, offers: FlightOffer[], status: ProviderStatus, error_message: ?string, duration_ms: int}>
     */
    protected function resolveLocal(): array
    {
        $results = [];

        foreach ($this->providers as $provider) {
            if (! str_starts_with($provider->endpoint(), '/api/internal/')) {
                continue;
            }

            $start = hrtime(true);
            $offers = [];
            $normalizationFailures = 0;

            /** @var array<int, array<string, mixed>> $rawOffers */
            $rawOffers = $provider->fixtures();

            foreach ($rawOffers as $raw) {
                try {
                    $offers[] = $provider->normalize($raw);
                } catch (\Throwable $e) {
                    report($e);
                    $normalizationFailures++;
                }
            }

            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            $status = match (true) {
                $normalizationFailures > 0 && count($offers) === 0 => ProviderStatus::ERROR,
                $normalizationFailures > 0 => ProviderStatus::PARTIAL,
                default => ProviderStatus::SUCCESS,
            };

            $results[] = [
                'provider_name' => $provider->name(),
                'offers' => $offers,
                'status' => $status,
                'duration_ms' => $durationMs,
                'error_message' => $normalizationFailures > 0 ? 'Some provider offers could not be normalized.' : null,
            ];
        }

        return $results;
    }

    /**
     * @param  array{from: string, to: string, date: string, passengers: int}  $params
     * @return array<int, array{provider_name: string, offers: FlightOffer[], status: ProviderStatus, error_message: ?string, duration_ms: int}>
     */
    protected function resolveRemote(array $params): array
    {
        $timeout = (int) config('providers.timeout', 5);
        $baseUrl = app()->runningInConsole()
            ? rtrim((string) config('app.url', 'http://localhost'), '/')
            : request()->schemeAndHttpHost();

        $remote = array_filter(
            $this->providers,
            fn ($p) => ! str_starts_with($p->endpoint(), '/api/internal/'),
        );

        if ($remote === []) {
            return [];
        }

        $names = array_keys($remote);

        $query = http_build_query([
            'from' => $params['from'],
            'to' => $params['to'],
            'date' => $params['date'],
            'passengers' => $params['passengers'],
        ]);

        $start = hrtime(true);

        try {
            $responses = Http::timeout($timeout)->pool(function (Pool $pool) use ($remote, $baseUrl, $query): void {
                foreach ($remote as $provider) {
                    $pool->as($provider->name())->get($baseUrl.$provider->endpoint().'?'.$query);
                }
            });
        } catch (ConnectionException $e) {
            report($e);

            $results = [];
            foreach ($names as $name) {
                $results[] = [
                    'provider_name' => $name,
                    'offers' => [],
                    'status' => ProviderStatus::TIMEOUT,
                    'duration_ms' => 0,
                    'error_message' => 'Provider request timed out.',
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            report($e);

            $results = [];
            foreach ($names as $name) {
                $results[] = [
                    'provider_name' => $name,
                    'offers' => [],
                    'status' => ProviderStatus::ERROR,
                    'duration_ms' => 0,
                    'error_message' => 'Provider request failed.',
                ];
            }

            return $results;
        }

        $totalDurationMs = (int) ((hrtime(true) - $start) / 1_000_000);
        $durationMs = (int) ($totalDurationMs / max(count($remote), 1));

        $results = [];

        foreach ($remote as $provider) {
            $name = $provider->name();
            $response = $responses[$name] ?? null;

            if ($response instanceof ConnectionException) {
                $results[] = [
                    'provider_name' => $name,
                    'offers' => [],
                    'status' => ProviderStatus::TIMEOUT,
                    'duration_ms' => $durationMs,
                    'error_message' => 'Provider request timed out.',
                ];

                continue;
            }

            if (! $response instanceof Response || ! $response->successful()) {
                $results[] = [
                    'provider_name' => $name,
                    'offers' => [],
                    'status' => ProviderStatus::ERROR,
                    'duration_ms' => $durationMs,
                    'error_message' => 'Provider request failed.',
                ];

                continue;
            }

            /** @var array<int, array<string, mixed>> $rawOffers */
            $rawOffers = $response->json($provider->responseKey()) ?? [];
            $offers = [];
            $normalizationFailures = 0;

            foreach ($rawOffers as $raw) {
                try {
                    $offers[] = $provider->normalize($raw);
                } catch (\Throwable $e) {
                    report($e);
                    $normalizationFailures++;
                }
            }

            $status = match (true) {
                $normalizationFailures > 0 && count($offers) === 0 => ProviderStatus::ERROR,
                $normalizationFailures > 0 => ProviderStatus::PARTIAL,
                default => ProviderStatus::SUCCESS,
            };

            $results[] = [
                'provider_name' => $name,
                'offers' => $offers,
                'status' => $status,
                'duration_ms' => $durationMs,
                'error_message' => $normalizationFailures > 0 ? 'Some provider offers could not be normalized.' : null,
            ];
        }

        return $results;
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
     * @param  array{filterMaxStops?: ?int, filterCarriers?: string[], filterMaxPrice?: ?float}  $params
     * @return FlightOffer[]
     */
    private function filter(array $offers, array $params): array
    {
        return array_values(array_filter($offers, function (FlightOffer $offer) use ($params): bool {
            if (($params['filterMaxStops'] ?? null) !== null && $offer->stops > $params['filterMaxStops']) {
                return false;
            }

            if (($params['filterCarriers'] ?? null) !== null && ! in_array(strtoupper($offer->carrier), $params['filterCarriers'], true)) {
                return false;
            }

            if (($params['filterMaxPrice'] ?? null) !== null && $offer->price > $params['filterMaxPrice']) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  FlightOffer[]  $offers
     * @param  array{sortField?: ?string, sortDirection?: ?string}  $params
     * @return FlightOffer[]
     */
    private function sort(array $offers, array $params): array
    {
        $field = ($params['sortField'] ?? null) !== null
            ? SortField::fromString($params['sortField'])
            : SortField::PRICE;
        $direction = ($params['sortDirection'] ?? null) !== null
            ? SortDirection::from($params['sortDirection'])
            : SortDirection::ASC;

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
