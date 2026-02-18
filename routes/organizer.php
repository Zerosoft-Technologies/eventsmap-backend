<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Organizer\AuthController;
use App\Http\Controllers\Organizer\EventController;

/*
|--------------------------------------------------------------------------
| Organizer API Routes
|--------------------------------------------------------------------------
|
| Event Organizer API endpoints with authentication
|
*/

// Public auth routes (no authentication required)
// Route::prefix('auth')->group(function () {
//     Route::post('/login', [AuthController::class, 'login']);
// });

// Protected organizer routes (authentication required)
// Route::middleware(['auth:sanctum', 'organizer'])->group(function () {
    Route::prefix('organizer/events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{id}', [EventController::class, 'show']);
        Route::put('/{id}', [EventController::class, 'update']);
        Route::delete('/{id}', [EventController::class, 'destroy']);

        // Event status actions
        Route::post('/{id}/publish', [EventController::class, 'publish']);
        Route::post('/{id}/unpublish', [EventController::class, 'unpublish']);
        Route::post('/{id}/feature', [EventController::class, 'feature']);
        Route::post('/{id}/unfeature', [EventController::class, 'unfeature']);
        Route::post('/{id}/cancel', [EventController::class, 'cancel']);
        Route::post('/{id}/archive', [EventController::class, 'archive']);

        // Event talents
        Route::get('/{id}/talents', [EventController::class, 'getTalents']);
        Route::post('/{id}/talents', [EventController::class, 'attachTalents']);
        Route::delete('/{id}/talents/{talentId}', [EventController::class, 'detachTalent']);
        Route::patch('/{id}/talents/reorder', [EventController::class, 'reorderTalents']);

        // Event media
        Route::get('/{id}/media', [EventController::class, 'getMedia']);
        Route::post('/{id}/media', [EventController::class, 'attachMedia']);
        Route::delete('/{id}/media/{mediaId}', [EventController::class, 'detachMedia']);
        Route::patch('/{id}/media/reorder', [EventController::class, 'reorderMedia']);
        Route::post('/{id}/media/primary', [EventController::class, 'setPrimaryMedia']);

        // Bulk operations
        Route::post('/bulk/delete', [EventController::class, 'bulkDelete']);
        Route::post('/bulk/archive', [EventController::class, 'bulkArchive']);
    });

    // Events CRUD
// });
