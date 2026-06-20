<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ProviderFixtureController extends Controller
{
    public function show(string $provider): JsonResponse
    {
        $providers = app('providers');
        $adapter = $providers[$provider] ?? null;

        if ($adapter === null) {
            return response()->json(['message' => 'Provider not found.'], 404);
        }

        return response()->json([$adapter->responseKey() => $adapter->fixtures()]);
    }
}
