<?php

namespace App\FlightSearch\Enums;

enum BookingStatus: string
{
    case CONFIRMED = 'confirmed';
    // other statuses like pending, cancelled, etc. can be added here as needed
}
