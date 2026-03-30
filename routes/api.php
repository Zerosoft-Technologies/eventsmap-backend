<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\UpgradePlanController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\GalleryImageController;

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

// Authenticated user routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Payment retry (for pending_payment users)
    Route::post('/payment/retry', [StripeController::class, 'retryPayment']);

    // Upgrade plan (for free users to upgrade to premium)
    Route::post('/user/upgrade-plan', [UpgradePlanController::class, 'upgrade']);

    // User profile management
    Route::get('/user/profile', [UserProfileController::class, 'show']);
    Route::put('/user/profile', [UserProfileController::class, 'update']);

    // Gallery routes (premium users only)
    Route::get('/gallery-images', [GalleryImageController::class, 'index'])->name('gallery.index');
    Route::post('/gallery-images/upload', [GalleryImageController::class, 'store'])->name('gallery.store');
    Route::delete('/gallery-images/{image_id}', [GalleryImageController::class, 'destroy'])->name('gallery.destroy');
});

// Premium-only routes (requires auth + active premium status)
Route::middleware(['auth:sanctum', 'premium.active'])->prefix('premium')->group(function () {
    // Add premium-only endpoints here
    // Example:
    // Route::get('/dashboard', [PremiumController::class, 'dashboard']);
    // Route::post('/events', [PremiumController::class, 'createEvent']);
});
