<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V2\EventController;
use App\Http\Controllers\V2\PublicEventController;
use App\Http\Controllers\V2\UserController;
use App\Http\Controllers\V2\WishlistController;
use App\Http\Controllers\V2\EventInvitationController;
use App\Http\Controllers\V2\ChatPermissionController;
use App\Http\Controllers\V2\FirebaseTokenController;

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

Route::get('/users', [UserController::class, 'index']);

Route::prefix('public')->group(function () {
    // Public event feed (map-ready, approved events only)
    Route::get('/events', [PublicEventController::class, 'index']);
    Route::get('/events/map', [PublicEventController::class, 'map']);
    Route::get('/events/{id}', [PublicEventController::class, 'show'])->where('id', '[0-9]+');
    Route::get('/events/slug/{slug}', [PublicEventController::class, 'showBySlug']);
});

// Respond to invitation (supports token-based from email link OR authenticated user)
Route::post('/event-invitations/{id}/respond', [EventInvitationController::class, 'respond']);

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

    // ──────────────────────────────────────
    // Event Invitations
    // ──────────────────────────────────────

    // Get invitations received by current user
    Route::get('/invitations', [EventInvitationController::class, 'index']);

    // Cancel an invitation (sender/owner only)
    Route::delete('/event-invitations/{id}', [EventInvitationController::class, 'destroy']);

    // Resend invitation email
    Route::post('/event-invitations/{id}/resend', [EventInvitationController::class, 'resend']);

    // Get invitations for a specific event (owner only)
    Route::get('/events/{event_id}/invitations', [EventInvitationController::class, 'eventInvitations']);

    // Manually trigger sending invitations for an event
    Route::post('/events/{event_id}/invitations/send', [EventInvitationController::class, 'sendInvitations']);

    // ──────────────────────────────────────
    // Chat Permissions
    // ──────────────────────────────────────

    // Get users who can participate in event chat
    Route::get('/events/{event_id}/chat-users', [ChatPermissionController::class, 'chatUsers']);

    // Check chat access for current user
    Route::get('/events/{event_id}/chat-access', [ChatPermissionController::class, 'chatAccess']);

    // Validate message before sending (rate limiting + permissions)
    Route::post('/events/{event_id}/chat/validate-message', [ChatPermissionController::class, 'validateMessage'])
        ->middleware('chat.ratelimit');

    // Report a message/user in chat
    Route::post('/events/{event_id}/chat/report', [ChatPermissionController::class, 'reportMessage']);

    // ──────────────────────────────────────
    // Chat Moderation (Event Owner Only)
    // ──────────────────────────────────────

    // Mute a user in event chat
    Route::post('/events/{event_id}/chat/mute', [ChatPermissionController::class, 'muteUser']);

    // Unmute a user in event chat
    Route::post('/events/{event_id}/chat/unmute', [ChatPermissionController::class, 'unmuteUser']);

    // Ban a user from event chat
    Route::post('/events/{event_id}/chat/ban', [ChatPermissionController::class, 'banUser']);

    // Unban a user from event chat
    Route::post('/events/{event_id}/chat/unban', [ChatPermissionController::class, 'unbanUser']);

    // ──────────────────────────────────────
    // Firebase Integration
    // ──────────────────────────────────────

    // Generate Firebase custom token
    Route::post('/firebase/token', [FirebaseTokenController::class, 'generateToken']);

    // Generate Firebase token with event-specific claims
    Route::post('/firebase/token/event/{event_id}', [FirebaseTokenController::class, 'generateEventToken']);

});
