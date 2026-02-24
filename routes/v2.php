<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V2\EventController;
use App\Http\Controllers\V2\PublicEventController;

/*
|--------------------------------------------------------------------------
| V2 API Routes
|--------------------------------------------------------------------------
|
| Version 2 API endpoints for the Events Map platform.
|
*/

// ──────────────────────────────────────
// Public Routes (No Authentication)
// ──────────────────────────────────────

Route::prefix('public')->group(function () {
    // Public event feed (map-ready, approved events only)
    Route::get('/events', [PublicEventController::class, 'index']);
    Route::get('/events/map', [PublicEventController::class, 'map']);
    Route::get('/events/{id}', [PublicEventController::class, 'show'])->where('id', '[0-9]+');
    Route::get('/events/slug/{slug}', [PublicEventController::class, 'showBySlug']);
});

// ──────────────────────────────────────
// Protected Routes (Authentication Required)
// ──────────────────────────────────────

Route::middleware(['auth:sanctum'])->group(function () {

    // Events CRUD (owner's events)
    Route::get('/events', [EventController::class, 'index']);
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);

});
