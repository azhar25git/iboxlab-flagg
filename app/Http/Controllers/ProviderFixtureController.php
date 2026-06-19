<?php

namespace App\Http\Controllers;

use App\FlightSearch\Services\ProviderRegistry;
use Illuminate\Http\JsonResponse;

class ProviderFixtureController extends Controller
{
    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    public function show(string $provider): JsonResponse
    {
        $adapter = $this->registry->all()[$provider] ?? null;

        if ($adapter === null) {
            return response()->json(['message' => 'Provider not found.'], 404);
        }

        return response()->json($adapter->fixtures());
    }
}
