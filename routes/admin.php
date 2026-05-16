<?php

use App\Http\Controllers\Admin\AdminEventV2Controller;
use App\Http\Controllers\Admin\AdminOrganiserV2Controller;
use App\Http\Controllers\Admin\AdminProfileV2TaxonomyController;
use App\Http\Controllers\Admin\AdminTalentV2Controller;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminVenueV2Controller;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BulkOperationsController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\OrganizerEventController;
use App\Http\Controllers\Admin\OrganizerEventDebugController;
use App\Http\Controllers\Admin\OrganizerEventFixedController;
use App\Http\Controllers\Admin\TalentController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

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

    // Organizer Events CRUD
    Route::prefix('organizer')->group(function () {
        Route::get('/', [OrganizerEventController::class, 'index']);
        Route::post('/', [OrganizerEventController::class, 'store']);
        Route::get('/{id}', [OrganizerEventController::class, 'show']);
        Route::put('/{id}', [OrganizerEventController::class, 'update']);
        Route::delete('/{id}', [OrganizerEventController::class, 'destroy']);

        // Fixed endpoint
        Route::get('/fixed', [OrganizerEventFixedController::class, 'index']);

        // Debug endpoints
        Route::get('/debug', [OrganizerEventDebugController::class, 'debug']);
        Route::get('/debug-with-relations', [OrganizerEventDebugController::class, 'debugWithRelations']);
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

    // V2 profile taxonomies (talent / organiser / venue categories & subcategories) — super-admin only
    Route::middleware('super_admin')->prefix('v2/profile-taxonomies')->group(function () {
        Route::get('/{profile}', [AdminProfileV2TaxonomyController::class, 'index'])
            ->whereIn('profile', ['talent', 'organiser', 'venue']);
        Route::post('/{profile}/categories', [AdminProfileV2TaxonomyController::class, 'storeCategory'])
            ->whereIn('profile', ['talent', 'organiser', 'venue']);
        Route::get('/{profile}/categories/{categoryId}', [AdminProfileV2TaxonomyController::class, 'showCategory'])
            ->whereIn('profile', ['talent', 'organiser', 'venue'])
            ->whereNumber('categoryId');
        Route::put('/{profile}/categories/{categoryId}', [AdminProfileV2TaxonomyController::class, 'updateCategory'])
            ->whereIn('profile', ['talent', 'organiser', 'venue'])
            ->whereNumber('categoryId');
        Route::delete('/{profile}/categories/{categoryId}', [AdminProfileV2TaxonomyController::class, 'destroyCategory'])
            ->whereIn('profile', ['talent', 'organiser', 'venue'])
            ->whereNumber('categoryId');
        Route::post('/{profile}/categories/{categoryId}/subcategories', [AdminProfileV2TaxonomyController::class, 'storeSubcategory'])
            ->whereIn('profile', ['talent', 'organiser', 'venue'])
            ->whereNumber('categoryId');
        Route::put('/{profile}/subcategories/{subcategoryId}', [AdminProfileV2TaxonomyController::class, 'updateSubcategory'])
            ->whereIn('profile', ['talent', 'organiser', 'venue'])
            ->whereNumber('subcategoryId');
        Route::delete('/{profile}/subcategories/{subcategoryId}', [AdminProfileV2TaxonomyController::class, 'destroySubcategory'])
            ->whereIn('profile', ['talent', 'organiser', 'venue'])
            ->whereNumber('subcategoryId');
    });

    // Admin Users Management
    Route::prefix('users')->group(function () {
        // Stats and listing (admin access)
        Route::get('/stats', [AdminUserController::class, 'stats']);
        Route::get('/', [AdminUserController::class, 'index']);
        Route::get('/{id}', [AdminUserController::class, 'show'])->whereNumber('id');

        // Status update (Super Admin only)
        Route::middleware('super_admin')->patch('/{id}/status', [AdminUserController::class, 'updateStatus']);

        // CRUD operations (Super Admin only) — back-office accounts; use GET /users/{id} for any user
        Route::middleware('super_admin')->group(function () {
            Route::post('/', [UserController::class, 'store']);
            Route::put('/{id}', [UserController::class, 'update'])->whereNumber('id');
            Route::patch('/{id}', [UserController::class, 'partialUpdate'])->whereNumber('id');
            Route::delete('/{id}', [UserController::class, 'destroy'])->whereNumber('id');
            Route::post('/{id}/activate', [UserController::class, 'activate'])->whereNumber('id');
            Route::post('/{id}/deactivate', [UserController::class, 'deactivate'])->whereNumber('id');
            Route::post('/{id}/reset-password', [UserController::class, 'resetPassword'])->whereNumber('id');
        });
    });

    // Back-office accounts (admin + super_admin); super_admin only
    Route::prefix('backoffice-users')->middleware('super_admin')->group(function () {
        Route::get('/', [UserController::class, 'backofficeIndex']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{id}', [UserController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [UserController::class, 'update'])->whereNumber('id');
        Route::patch('/{id}', [UserController::class, 'partialUpdate'])->whereNumber('id');
        Route::delete('/{id}', [UserController::class, 'destroy'])->whereNumber('id');
        Route::post('/{id}/activate', [UserController::class, 'activate'])->whereNumber('id');
        Route::post('/{id}/deactivate', [UserController::class, 'deactivate'])->whereNumber('id');
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword'])->whereNumber('id');
    });

    // Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('/events', [AnalyticsController::class, 'events']);
        Route::get('/categories', [AnalyticsController::class, 'categories']);
    });

    // Media Management
    Route::prefix('media')->group(function () {
        Route::post('/upload', [MediaController::class, 'upload']);
        Route::delete('/', [MediaController::class, 'delete']);
        Route::get('/', [MediaController::class, 'list']);
        Route::put('/{id}', [MediaController::class, 'update']);
    });

    // ──────────────────────────────────────
    // V2 Events Admin Management
    // ──────────────────────────────────────
    Route::prefix('events-v2')->group(function () {
        // Listing, stats, and filtered views
        Route::get('/', [AdminEventV2Controller::class, 'index']);
        Route::get('/stats', [AdminEventV2Controller::class, 'stats']);
        Route::get('/pending', [AdminEventV2Controller::class, 'pending']);
        Route::get('/trashed', [AdminEventV2Controller::class, 'trashed']);

        // Bulk operations
        Route::post('/bulk-approve', [AdminEventV2Controller::class, 'bulkApprove']);
        Route::post('/bulk-delete', [AdminEventV2Controller::class, 'bulkDelete']);

        // Invited entities relationships (must be before /{id})
        Route::patch('/{id}/talents/reorder', [AdminEventV2Controller::class, 'reorderTalents']);
        Route::get('/{id}/talents', [AdminEventV2Controller::class, 'talents']);
        Route::post('/{id}/talents', [AdminEventV2Controller::class, 'syncTalents']);
        Route::delete('/{id}/talents/{talentId}', [AdminEventV2Controller::class, 'detachTalent']);
        Route::get('/{id}/media', [AdminEventV2Controller::class, 'getMedia']);
        Route::post('/{id}/media', [AdminEventV2Controller::class, 'attachMedia']);
        Route::delete('/{id}/media/{mediaId}', [AdminEventV2Controller::class, 'detachMedia']);
        Route::get('/{id}/organisers', [AdminEventV2Controller::class, 'organisers']);
        Route::get('/{id}/venues', [AdminEventV2Controller::class, 'venues']);

        // CRUD
        Route::post('/', [AdminEventV2Controller::class, 'store']);
        Route::get('/{id}', [AdminEventV2Controller::class, 'show']);
        Route::put('/{id}', [AdminEventV2Controller::class, 'update']);
        Route::delete('/{id}', [AdminEventV2Controller::class, 'destroy']);

        // Moderation actions
        Route::patch('/{id}/status', [AdminEventV2Controller::class, 'updateStatus']);
        Route::post('/{id}/approve', [AdminEventV2Controller::class, 'approve']);
        Route::post('/{id}/unapprove', [AdminEventV2Controller::class, 'unapprove']);
        Route::post('/{id}/suspend', [AdminEventV2Controller::class, 'suspend']);
        Route::post('/{id}/unsuspend', [AdminEventV2Controller::class, 'unsuspend']);

        // Trash management
        Route::patch('/{id}/restore', [AdminEventV2Controller::class, 'restore']);
        Route::delete('/{id}/force', [AdminEventV2Controller::class, 'forceDelete']);
    });

    // ──────────────────────────────────────
    // V2 Organisers Admin Management
    // ──────────────────────────────────────
    Route::prefix('organisers-v2')->group(function () {
        Route::get('/', [AdminOrganiserV2Controller::class, 'index']);
        Route::get('/stats', [AdminOrganiserV2Controller::class, 'stats']);

        // Bulk operations
        Route::post('/bulk-approve', [AdminOrganiserV2Controller::class, 'bulkApprove']);
        Route::post('/bulk-delete', [AdminOrganiserV2Controller::class, 'bulkDelete']);

        // CRUD
        Route::post('/', [AdminOrganiserV2Controller::class, 'store']);
        Route::get('/{id}', [AdminOrganiserV2Controller::class, 'show']);
        Route::put('/{id}', [AdminOrganiserV2Controller::class, 'update']);
        Route::delete('/{id}', [AdminOrganiserV2Controller::class, 'destroy']);

        // Moderation
        Route::post('/{id}/approve', [AdminOrganiserV2Controller::class, 'approve']);
        Route::post('/{id}/unapprove', [AdminOrganiserV2Controller::class, 'unapprove']);
    });

    // ──────────────────────────────────────
    // V2 Talents Admin Management
    // ──────────────────────────────────────
    Route::prefix('talents-v2')->group(function () {
        Route::get('/', [AdminTalentV2Controller::class, 'index']);
        Route::get('/stats', [AdminTalentV2Controller::class, 'stats']);

        // Bulk operations
        Route::post('/bulk-approve', [AdminTalentV2Controller::class, 'bulkApprove']);
        Route::post('/bulk-delete', [AdminTalentV2Controller::class, 'bulkDelete']);

        // CRUD
        Route::post('/', [AdminTalentV2Controller::class, 'store']);
        Route::get('/{id}', [AdminTalentV2Controller::class, 'show']);
        Route::put('/{id}', [AdminTalentV2Controller::class, 'update']);
        Route::delete('/{id}', [AdminTalentV2Controller::class, 'destroy']);

        // Moderation
        Route::post('/{id}/approve', [AdminTalentV2Controller::class, 'approve']);
        Route::post('/{id}/unapprove', [AdminTalentV2Controller::class, 'unapprove']);
    });

    // ──────────────────────────────────────
    // V2 Venues Admin Management
    // ──────────────────────────────────────
    Route::prefix('venues-v2')->group(function () {
        Route::get('/', [AdminVenueV2Controller::class, 'index']);
        Route::get('/stats', [AdminVenueV2Controller::class, 'stats']);

        // Bulk operations
        Route::post('/bulk-approve', [AdminVenueV2Controller::class, 'bulkApprove']);
        Route::post('/bulk-delete', [AdminVenueV2Controller::class, 'bulkDelete']);

        // CRUD
        Route::post('/', [AdminVenueV2Controller::class, 'store']);
        Route::get('/{id}', [AdminVenueV2Controller::class, 'show']);
        Route::put('/{id}', [AdminVenueV2Controller::class, 'update']);
        Route::delete('/{id}', [AdminVenueV2Controller::class, 'destroy']);

        // Moderation
        Route::post('/{id}/approve', [AdminVenueV2Controller::class, 'approve']);
        Route::post('/{id}/unapprove', [AdminVenueV2Controller::class, 'unapprove']);
    });

    // Organizer API routes
    require __DIR__.'/organizer.php';
});
