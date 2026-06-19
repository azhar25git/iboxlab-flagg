<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    private const PREFIX = 'IBX-';

    private const ULID_LENGTH = 26;

    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'flight_id',
        'flight_snapshot',
        'passengers',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'flight_snapshot' => 'array',
            'passengers' => 'array',
        ];
    }

    public static function generateReference(): string
    {
        return self::PREFIX.Str::ulid();
    }

    public static function referencePattern(): string
    {
        return '/^'.self::PREFIX.'[0-9A-HJKMNP-TV-Z]{'.self::ULID_LENGTH.'}$/';
    }

    public static function isValidReference(string $reference): bool
    {
        return (bool) preg_match(self::referencePattern(), $reference);
    }
}
