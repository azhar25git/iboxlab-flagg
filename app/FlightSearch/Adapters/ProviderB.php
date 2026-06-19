<?php

namespace App\FlightSearch\Adapters;

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Services\FlightNormalizer;
use App\FlightSearch\ValueObjects\FlightOffer;
use App\FlightSearch\ValueObjects\ProviderResultSet;
use App\FlightSearch\ValueObjects\SearchRequest;

class ProviderB implements ProviderContract
{
    public function __construct(
        private readonly FlightNormalizer $normalizer,
    ) {}

    public function name(): string
    {
        return 'ProviderB';
    }

    public function search(SearchRequest $request): ProviderResultSet
    {
        $start = hrtime(true);

        $fixtures = $this->fixtures();

        $offers = array_map(
            fn (array $raw): FlightOffer => $this->normalizer->normalizeProviderB($raw),
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
    private function fixtures(): array
    {
        return [
            [
                'airline_code' => 'BS', 'origin' => 'DAC', 'destination' => 'DXB',
                'departure_time' => '2026-07-01 09:15', 'arrival_time' => '2026-07-01 15:00',
                'segments' => 1, 'price' => ['amount' => 295, 'currency' => 'USD'], 'number' => 'BS220',
            ],
            [
                'airline_code' => 'BS', 'origin' => 'DAC', 'destination' => 'DXB',
                'departure_time' => '2026-07-01 14:30', 'arrival_time' => '2026-07-01 19:20',
                'segments' => 1, 'price' => ['amount' => 265, 'currency' => 'USD'], 'number' => 'BS118',
            ],
            [
                'airline_code' => 'EK', 'origin' => 'DAC', 'destination' => 'DXB',
                'departure_time' => '2026-07-01 03:45', 'arrival_time' => '2026-07-01 06:50',
                'segments' => 0, 'price' => ['amount' => 399, 'currency' => 'USD'], 'number' => 'EK585',
            ],
        ];
    }
}
