<?php

namespace App\FlightSearch\Services;

class ReferenceGenerator
{
    public function generate(): string
    {
        $alpha = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

        return 'IBX-'.$alpha;
    }
}
