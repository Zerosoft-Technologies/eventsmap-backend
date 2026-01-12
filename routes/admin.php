<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\TalentController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BulkOperationsController;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Admin Panel API endpoints with authentication
|
*/

// Public auth routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected admin routes (authentication required)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // Events CRUD
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::post('/', [EventController::class, 'store']);
        Route::get('/{id}', [EventController::class, 'show']);
        Route::put('/{id}', [EventController::class, 'update']);
        Route::patch('/{id}', [EventController::class, 'partialUpdate']);
        Route::delete('/{id}', [EventController::class, 'destroy']);

        // Event status actions
        Route::post('/{id}/restore', [EventController::class, 'restore']);
        Route::post('/{id}/duplicate', [EventController::class, 'duplicate']);
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
        Route::post('/bulk/delete', [BulkOperationsController::class, 'deleteEvents']);
        Route::post('/bulk/publish', [BulkOperationsController::class, 'publishEvents']);
        Route::post('/bulk/unpublish', [BulkOperationsController::class, 'unpublishEvents']);
        Route::post('/bulk/archive', [BulkOperationsController::class, 'archiveEvents']);
        Route::post('/bulk/category', [BulkOperationsController::class, 'updateCategory']);
        Route::post('/bulk/feature', [BulkOperationsController::class, 'featureEvents']);
        Route::post('/bulk/unfeature', [BulkOperationsController::class, 'unfeatureEvents']);
    });

    // Talents CRUD
    Route::prefix('talents')->group(function () {
        Route::get('/', [TalentController::class, 'index']);
        Route::post('/', [TalentController::class, 'store']);
        Route::get('/{id}', [TalentController::class, 'show']);
        Route::put('/{id}', [TalentController::class, 'update']);
        Route::patch('/{id}', [TalentController::class, 'partialUpdate']);
        Route::delete('/{id}', [TalentController::class, 'destroy']);
        Route::post('/{id}/restore', [TalentController::class, 'restore']);
        Route::post('/{id}/activate', [TalentController::class, 'activate']);
        Route::post('/{id}/deactivate', [TalentController::class, 'deactivate']);
        Route::get('/{id}/events', [TalentController::class, 'getEvents']);
    });

    // Categories CRUD
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/{id}', [CategoryController::class, 'show']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
        Route::post('/{id}/activate', [CategoryController::class, 'activate']);
        Route::post('/{id}/deactivate', [CategoryController::class, 'deactivate']);
        Route::patch('/reorder', [CategoryController::class, 'reorder']);
        Route::get('/{id}/subcategories', [CategoryController::class, 'getSubcategories']);
    });

    // Subcategories
    Route::prefix('subcategories')->group(function () {
        Route::put('/{id}', [CategoryController::class, 'updateSubcategory']);
        Route::delete('/{id}', [CategoryController::class, 'destroySubcategory']);
    });

    // Admin Users (Super Admin only)
    Route::middleware('super_admin')->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::post('/{id}/activate', [UserController::class, 'activate']);
        Route::post('/{id}/deactivate', [UserController::class, 'deactivate']);
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
    });

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('/events', [AnalyticsController::class, 'events']);
        Route::get('/categories', [AnalyticsController::class, 'categories']);
    });
});
