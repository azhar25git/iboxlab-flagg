<?php

namespace App\FlightSearch\Services;

use Illuminate\Support\Str;

class ReferenceGenerator
{
    private const PREFIX = 'IBX-';

    private const ULID_LENGTH = 26;

    public function generate(): string
    {
        return self::PREFIX.Str::ulid();
    }

    public static function pattern(): string
    {
        // Crockford base32: 0-9 A-Z excluding I, L, O, U
        return '/^'.self::PREFIX.'[0-9A-HJKMNP-TV-Z]{'.self::ULID_LENGTH.'}$/';
    }

    public static function isValid(string $reference): bool
    {
        return (bool) preg_match(self::pattern(), $reference);
    }
}
