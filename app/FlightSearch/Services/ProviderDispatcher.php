<?php

namespace App\FlightSearch\Services;

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\ValueObjects\ProviderResultSet;
use App\FlightSearch\ValueObjects\SearchRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ProviderDispatcher
{
    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    /**
     * @return ProviderResultSet[]
     */
    /**
     * @return ProviderResultSet[]
     */
    public function dispatch(SearchRequest $request): array
    {
        $providers = $this->registry->all();

        return array_merge(
            $this->resolveLocal($providers),
            $this->resolveRemote($request, $providers),
        );
    }

    /**
     * Resolve providers whose endpoint starts with /api/internal/
     * directly in-process (no HTTP call). This avoids self-deadlock
     * on single-threaded dev servers.
     *
     * @param  array<string, ProviderContract>  $providers
     * @return ProviderResultSet[]
     */
    private function resolveLocal(array $providers): array
    {
        $results = [];

        foreach ($providers as $provider) {
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

            $results[] = new ProviderResultSet(
                providerName: $provider->name(),
                offers: $offers,
                status: $status,
                durationMs: $durationMs,
                errorMessage: $normalizationFailures > 0 ? 'Some provider offers could not be normalized.' : null,
            );
        }

        return $results;
    }

    /**
     * Resolve external providers via concurrent HTTP calls.
     *
     * @param  array<string, ProviderContract>  $providers
     * @return ProviderResultSet[]
     */
    private function resolveRemote(SearchRequest $request, array $providers): array
    {
        $timeout = (int) config('providers.timeout', 5);
        $baseUrl = app()->runningInConsole()
            ? rtrim((string) config('app.url', 'http://localhost'), '/')
            : request()->schemeAndHttpHost();

        $remote = array_filter(
            $providers,
            fn ($p) => ! str_starts_with($p->endpoint(), '/api/internal/'),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($remote === []) {
            return [];
        }

        $names = array_keys($remote);

        $query = http_build_query([
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
            'passengers' => $request->passengers,
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

            return array_map(
                fn (string $name): ProviderResultSet => new ProviderResultSet(
                    providerName: $name,
                    offers: [],
                    status: ProviderStatus::TIMEOUT,
                    errorMessage: 'Provider request timed out.',
                ),
                $names,
            );
        } catch (\Throwable $e) {
            report($e);

            return array_map(
                fn (string $name): ProviderResultSet => new ProviderResultSet(
                    providerName: $name,
                    offers: [],
                    status: ProviderStatus::ERROR,
                    errorMessage: 'Provider request failed.',
                ),
                $names,
            );
        }

        $totalDurationMs = (int) ((hrtime(true) - $start) / 1_000_000);
        $durationMs = (int) ($totalDurationMs / max(count($remote), 1));

        $results = [];

        foreach ($remote as $provider) {
            $name = $provider->name();
            $response = $responses[$name] ?? null;

            if ($response instanceof ConnectionException) {
                $results[] = new ProviderResultSet(
                    providerName: $name,
                    offers: [],
                    status: ProviderStatus::TIMEOUT,
                    durationMs: $durationMs,
                    errorMessage: 'Provider request timed out.',
                );

                continue;
            }

            if (! $response instanceof Response || ! $response->successful()) {
                $results[] = new ProviderResultSet(
                    providerName: $name,
                    offers: [],
                    status: ProviderStatus::ERROR,
                    durationMs: $durationMs,
                    errorMessage: 'Provider request failed.',
                );

                continue;
            }

            /** @var array<int, array<string, mixed>> $rawOffers */
            $rawOffers = array_values($response->json() ?? []);
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

            $results[] = new ProviderResultSet(
                providerName: $name,
                offers: $offers,
                status: $status,
                durationMs: $durationMs,
                errorMessage: $normalizationFailures > 0 ? 'Some provider offers could not be normalized.' : null,
            );
        }

        return $results;
    }
}
