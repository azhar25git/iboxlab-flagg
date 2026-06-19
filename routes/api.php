<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\FlightSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/flights/search', [FlightSearchController::class, 'search']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings/{reference}', [BookingController::class, 'show']);

Route::get('/docs/openapi.yaml', fn () => response()->file(
    storage_path('api-docs/api.yaml'),
    ['Content-Type' => 'application/x-yaml'],
));
