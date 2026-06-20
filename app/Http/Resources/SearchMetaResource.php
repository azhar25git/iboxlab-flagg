<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchMetaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'providers' => $this->resource['providers'],
            'total_flights' => $this->resource['total_flights'],
            'unique_flights' => $this->resource['unique_flights'],
            'passengers' => $this->resource['passengers'],
            'currency' => $this->resource['currency'],
            'price_unit' => $this->resource['price_unit'],
        ];
    }
}
