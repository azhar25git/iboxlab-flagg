<?php

namespace App\FlightSearch\Adapters;

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Services\FlightNormalizer;
use App\FlightSearch\ValueObjects\FlightOffer;
use App\FlightSearch\ValueObjects\ProviderResultSet;
use App\FlightSearch\ValueObjects\SearchRequest;

class ProviderC implements ProviderContract
{
    public function __construct(
        private readonly FlightNormalizer $normalizer,
    ) {}

    public function name(): string
    {
        return 'ProviderC';
    }

    public function endpoint(): string
    {
        return '/api/internal/providers/ProviderC/fixtures';
    }

    public function search(SearchRequest $request): ProviderResultSet
    {
        $start = hrtime(true);

        $fixtures = $this->fixtures();

        $offers = array_map(
            fn (array $raw): FlightOffer => $this->normalizer->normalizeProviderC($raw),
            $fixtures,
        );

        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

        return new ProviderResultSet(
            providerName: $this->name(),
            offers: $offers,
            status: ProviderStatus::SUCCESS,
            durationMs: $durationMs,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fixtures(): array
    {
        return [
            [
                'iata' => 'AA', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
                'times' => ['dep' => 1782892800, 'arr' => 1782909000],
                'layovers' => 0, 'total_price' => 335, 'currency' => 'USD', 'code' => 'AA101',
            ],
            [
                'iata' => 'CJ', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
                'times' => ['dep' => 1782885600, 'arr' => 1782903600],
                'layovers' => 2, 'total_price' => 270, 'currency' => 'USD', 'code' => 'CJ300',
            ],
            [
                'iata' => 'EK', 'route' => ['src' => 'DAC', 'dst' => 'DXB'],
                'times' => ['dep' => 1782877500, 'arr' => 1782888600],
                'layovers' => 0, 'total_price' => 405, 'currency' => 'USD', 'code' => 'EK585',
            ],
        ];
    }
}
