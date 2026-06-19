<?php

namespace App\FlightSearch\Services;

use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\ValueObjects\FlightOffer;
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
    public function dispatch(SearchRequest $request): array
    {
        $timeout = (int) config('providers.timeout', 5);
        $baseUrl = rtrim((string) config('app.url'), '/');

        $providers = $this->registry->all();
        $names = array_keys($providers);

        $query = http_build_query([
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
            'passengers' => $request->passengers,
        ]);

        $start = hrtime(true);

        try {
            $responses = Http::timeout($timeout)->pool(function (Pool $pool) use ($providers, $baseUrl, $query): void {
                foreach ($providers as $provider) {
                    $pool->as($provider->name())->get($baseUrl.$provider->endpoint().'?'.$query);
                }
            });
        } catch (ConnectionException $e) {
            report($e);

            // The whole pool failed (e.g. DNS / transport issue). Treat every provider as timed out.
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
        $durationMs = count($providers) > 0 ? (int) ($totalDurationMs / count($providers)) : 0;

        $results = [];

        foreach ($providers as $provider) {
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

            $offers = array_map(
                fn (array $raw): FlightOffer => $provider->normalize($raw),
                array_values($response->json() ?? []),
            );

            $results[] = new ProviderResultSet(
                providerName: $name,
                offers: $offers,
                status: ProviderStatus::SUCCESS,
                durationMs: $durationMs,
            );
        }

        return $results;
    }
}
