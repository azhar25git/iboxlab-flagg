<?php

namespace App\Http\Resources;

use App\FlightSearch\ValueObjects\ProviderResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProviderResult */
class ProviderMetaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = [
            'name' => $this->name,
            'status' => $this->status,
            'offers' => $this->offers,
            'duration_ms' => $this->durationMs,
        ];

        if ($this->errorMessage !== null) {
            $result['error_message'] = $this->errorMessage;
        }

        return $result;
    }
}
