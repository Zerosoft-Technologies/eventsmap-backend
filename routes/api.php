<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\StripeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Events Map API endpoints
|
*/

Route::prefix('v1')->group(function () {
    // Events endpoints
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    Route::get('/events/{id}/talents', [EventController::class, 'talents']);
    Route::get('/events/{id}/about', [EventController::class, 'about']);
    Route::get('/events/{id}/location', [EventController::class, 'location']);
    Route::get('/events/{id}/images', [EventController::class, 'images']);

    // Categories endpoints
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);
    Route::get('/categories/{slug}/subcategories', [CategoryController::class, 'subcategories']);
});

// Payment verification (no auth required)
Route::post('/payment/verify', [StripeController::class, 'verifySession']);

// Stripe webhook (no auth, no CSRF)
Route::post('/webhook/stripe', [StripeController::class, 'webhook']);

// Retry payment (auth required, for pending_payment users)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/payment/retry', [StripeController::class, 'retryPayment']);
});

// Premium-only routes (requires auth + active premium status)
Route::middleware(['auth:sanctum', 'premium.active'])->prefix('premium')->group(function () {
    // Add premium-only endpoints here
    // Example:
    // Route::get('/dashboard', [PremiumController::class, 'dashboard']);
    // Route::post('/events', [PremiumController::class, 'createEvent']);
});
