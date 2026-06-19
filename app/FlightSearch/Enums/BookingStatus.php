<?php

namespace App\FlightSearch\Enums;

enum BookingStatus: string
{
    case CONFIRMED = 'confirmed';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
}
