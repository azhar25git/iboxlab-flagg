<?php

namespace App\FlightSearch\Enums;

enum ProviderStatus: string
{
    case SUCCESS = 'success';
    case TIMEOUT = 'timeout';
    case ERROR = 'error';
    case PARTIAL = 'partial';
}
