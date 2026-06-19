<?php

namespace App\FlightSearch\Services;

use App\FlightSearch\ValueObjects\FlightOffer;
use Illuminate\Contracts\Cache\Repository;

class FlightOfferRepository
{
    private const TTL_SECONDS = 60;

    private const KEY_PREFIX = 'flight_offer:';

    public function __construct(
        private readonly Repository $cache,
    ) {}

    public function remember(FlightOffer $offer): void
    {
        $this->cache->put($this->key($offer->id), $offer, self::TTL_SECONDS);
    }

    public function find(string $id): ?FlightOffer
    {
        $offer = $this->cache->get($this->key($id));

        return $offer instanceof FlightOffer ? $offer : null;
    }

    public function forget(string $id): void
    {
        $this->cache->forget($this->key($id));
    }

    private function key(string $id): string
    {
        return self::KEY_PREFIX.$id;
    }
}
