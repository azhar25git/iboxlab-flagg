<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
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
}
