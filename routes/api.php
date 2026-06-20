<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\FlightSearchController;
use App\Http\Controllers\ProviderFixtureController;
use Illuminate\Support\Facades\Route;

Route::get('/flights/search', [FlightSearchController::class, 'search']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings/{reference}', [BookingController::class, 'show']);
Route::get('/internal/providers/{provider}/fixtures', [ProviderFixtureController::class, 'show']);
