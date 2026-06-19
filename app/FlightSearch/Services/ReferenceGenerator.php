<?php

namespace App\FlightSearch\Services;

class ReferenceGenerator
{
    private const PREFIX = 'IBX-';

    private const CHARS = 4;

    public function generate(): string
    {
        $alpha = strtoupper(substr(bin2hex(random_bytes(3)), 0, self::CHARS));

        return self::PREFIX.$alpha;
    }

    public static function pattern(): string
    {
        return '/^'.self::PREFIX.'[A-F0-9]{'.self::CHARS.'}$/';
    }

    public static function isValid(string $reference): bool
    {
        return (bool) preg_match(self::pattern(), $reference);
    }
}
