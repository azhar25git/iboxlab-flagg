<?php

namespace App\FlightSearch\Adapters;

use App\FlightSearch\Contracts\ProviderContract;
use App\FlightSearch\Enums\ProviderStatus;
use App\FlightSearch\Services\FlightNormalizer;
use App\FlightSearch\ValueObjects\FlightOffer;
use App\FlightSearch\ValueObjects\ProviderResultSet;
use App\FlightSearch\ValueObjects\SearchRequest;

class ProviderA implements ProviderContract
{
    public function __construct(
        private readonly FlightNormalizer $normalizer,
    ) {}

    public function name(): string
    {
        return 'ProviderA';
    }

    public function search(SearchRequest $request): ProviderResultSet
    {
        $start = hrtime(true);

        $fixtures = $this->fixtures();

        $offers = array_map(
            fn (array $raw): FlightOffer => $this->normalizer->normalizeProviderA($raw),
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
                'carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB',
                'depart' => '2026-07-01T08:00:00', 'arrive' => '2026-07-01T12:30:00',
                'stops' => 0, 'fare_usd' => 320.00, 'flight_no' => 'AA101',
            ],
            [
                'carrier' => 'AA', 'from' => 'DAC', 'to' => 'DXB',
                'depart' => '2026-07-01T22:10:00', 'arrive' => '2026-07-02T02:40:00',
                'stops' => 0, 'fare_usd' => 280.00, 'flight_no' => 'AA205',
            ],
            [
                'carrier' => 'BS', 'from' => 'DAC', 'to' => 'DXB',
                'depart' => '2026-07-01T09:15:00', 'arrive' => '2026-07-01T15:00:00',
                'stops' => 1, 'fare_usd' => 310.00, 'flight_no' => 'BS220',
            ],
            [
                'carrier' => 'EK', 'from' => 'DAC', 'to' => 'DXB',
                'depart' => '2026-07-01T03:45:00', 'arrive' => '2026-07-01T06:50:00',
                'stops' => 0, 'fare_usd' => 410.00, 'flight_no' => 'EK585',
            ],
        ];
    }
}
