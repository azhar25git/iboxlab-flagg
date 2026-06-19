<?php

namespace App\FlightSearch\ValueObjects;

use App\FlightSearch\Enums\ProviderStatus;

readonly class ProviderResultSet
{
    /**
     * @param  FlightOffer[]  $offers
     */
    public function __construct(
        public string $providerName,
        public array $offers,
        public ProviderStatus $status,
        public ?string $errorMessage = null,
        public int $durationMs = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function toMetaArray(): array
    {
        $meta = [
            'name' => $this->providerName,
            'status' => $this->status->value,
            'offers' => count($this->offers),
            'duration_ms' => $this->durationMs,
        ];

        if ($this->errorMessage !== null) {
            $meta['error_message'] = $this->errorMessage;
        }

        return $meta;
    }
}
