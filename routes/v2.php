<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V2\EventController;
use App\Http\Controllers\V2\PublicEventController;
use App\Http\Controllers\V2\WishlistController;

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

Route::get('/events', [EventController::class, 'index']);

Route::middleware(['auth:sanctum'])->group(function () {

    // My Events (sidebar)
    Route::get('/my-events', [EventController::class, 'myEvents']);

    // My Wishlist
    Route::get('/my-wishlist', [WishlistController::class, 'index']);

    // Events CRUD (owner's events)
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);

    // Wishlist toggle
    Route::post('/events/{id}/wishlist', [WishlistController::class, 'toggle']);

});
