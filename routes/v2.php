<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V2\EventController;
use App\Http\Controllers\V2\ProfileListingController;
use App\Http\Controllers\V2\PublicEventController;
use App\Http\Controllers\V2\PublicProfileController;
use App\Http\Controllers\V2\OrganiserController;
use App\Http\Controllers\V2\TalentController;
use App\Http\Controllers\V2\VenueController;
use App\Http\Controllers\V2\UserController;
use App\Http\Controllers\V2\WishlistController;
use App\Http\Controllers\V2\EventInvitationController;
use App\Http\Controllers\V2\EventGuestInvitationController;
use App\Http\Controllers\V2\EventInvitationProfileController;
use App\Http\Controllers\V2\ChatController;
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

    Route::get('/talents', [PublicProfileController::class, 'talents']);
    Route::get('/talents/{id}', [PublicProfileController::class, 'showTalent'])->whereNumber('id');
    Route::get('/organisers', [PublicProfileController::class, 'organisers']);
    Route::get('/organisers/{id}', [PublicProfileController::class, 'showOrganiser'])->whereNumber('id');
    Route::get('/venues', [PublicProfileController::class, 'venues']);
    Route::get('/venues/{id}', [PublicProfileController::class, 'showVenue'])->whereNumber('id');
});

// Respond to invitation (supports token-based from email link OR authenticated user)
Route::post('/event-invitations/{id}/respond', [EventInvitationController::class, 'respond']);

// Guest invitation token (registration prefill; no auth)
Route::get('/guest-invitations/token/{token}', [EventGuestInvitationController::class, 'showByToken'])
    ->where('token', '[A-Za-z0-9]+');

// ──────────────────────────────────────
// Protected Routes (Authentication Required)
// ──────────────────────────────────────

Route::get('/events', [EventController::class, 'index']);

Route::get('/talents', [ProfileListingController::class, 'talents']);
Route::get('/organisers', [ProfileListingController::class, 'organisers']);
Route::get('/venues', [ProfileListingController::class, 'venues']);

Route::middleware(['auth:sanctum'])->group(function () {

    // My Events (sidebar)
    Route::get('/my-events', [EventController::class, 'myEvents']);

    // My Organisers (sidebar)
    Route::get('/my-organisers', [OrganiserController::class, 'myOrganisers']);

    // My Talents (sidebar)
    Route::get('/my-talents', [TalentController::class, 'myTalents']);

    // My Venues (sidebar)
    Route::get('/my-venues', [VenueController::class, 'myVenues']);

    // My Wishlist
    Route::get('/my-wishlist', [WishlistController::class, 'index']);

    // Invitation picker (published talent / organiser / venue profiles)
    Route::get('/invitation-profiles', [EventInvitationProfileController::class, 'index']);
    Route::get('/events/{eventId}/invitation-profiles', [EventInvitationProfileController::class, 'forEvent'])
        ->whereNumber('eventId');

    // Events CRUD (owner's events)
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);

    // Organisers CRUD
    Route::post('/organisers', [OrganiserController::class, 'store']);
    Route::get('/organisers/{id}', [OrganiserController::class, 'show']);
    Route::put('/organisers/{id}', [OrganiserController::class, 'update']);
    Route::delete('/organisers/{id}', [OrganiserController::class, 'destroy']);

    // Talents CRUD
    Route::post('/talents', [TalentController::class, 'store']);
    Route::get('/talents/{id}', [TalentController::class, 'show']);
    Route::put('/talents/{id}', [TalentController::class, 'update']);
    Route::delete('/talents/{id}', [TalentController::class, 'destroy']);

    // Venues CRUD
    Route::post('/venues', [VenueController::class, 'store']);
    Route::get('/venues/{id}', [VenueController::class, 'show']);
    Route::put('/venues/{id}', [VenueController::class, 'update']);
    Route::delete('/venues/{id}', [VenueController::class, 'destroy']);

    Route::patch('/talents/{id}/status', [TalentController::class, 'updateStatus'])->whereNumber('id');
    Route::patch('/venues/{id}/status', [VenueController::class, 'updateStatus'])->whereNumber('id');
    Route::patch('/organisers/{id}/status', [OrganiserController::class, 'updateStatus'])->whereNumber('id');

    Route::patch('/talents/{id}/publish-status', [TalentController::class, 'updatePublishStatus'])->whereNumber('id');
    Route::patch('/venues/{id}/publish-status', [VenueController::class, 'updatePublishStatus'])->whereNumber('id');
    Route::patch('/organisers/{id}/publish-status', [OrganiserController::class, 'updatePublishStatus'])->whereNumber('id');
    Route::patch('/events/{id}/publish-status', [EventController::class, 'updatePublishStatus'])->whereNumber('id');

    Route::get('/meta/profile-publication-statuses', function () {
        return response()->json([
            'success' => true,
            'data' => [
                'statuses' => \App\Support\ProfilePublicationStatus::ALL,
                'sidebar_options' => \App\Support\ProfilePublicationStatus::SIDEBAR_OPTIONS,
                'labels' => \App\Support\ProfilePublicationStatus::labels(),
            ],
        ]);
    });

    Route::get('/meta/publish-statuses', function () {
        return response()->json([
            'success' => true,
            'data' => [
                'statuses' => \App\Support\PublishStatus::ALL,
                'labels' => \App\Support\PublishStatus::labels(),
            ],
        ]);
    });

    // Wishlist toggle
    Route::post('/events/{id}/wishlist', [WishlistController::class, 'toggle']);

    // ──────────────────────────────────────
    // Event Invitations
    // ──────────────────────────────────────

    // Get invitations received by current user (paginated, full EventV2 payloads)
    Route::get('/invitations', [EventInvitationController::class, 'index']);
    Route::get('/invited-events', [EventInvitationController::class, 'index']);

    // Accept / reject invitation (authenticated app; token-free)
    Route::post('/invitations/{id}/respond', [EventInvitationController::class, 'respondAuthenticated'])
        ->whereNumber('id');

    // Cancel an invitation (sender/owner only)
    Route::delete('/event-invitations/{id}', [EventInvitationController::class, 'destroy']);

    // Resend invitation email
    Route::post('/event-invitations/{id}/resend', [EventInvitationController::class, 'resend']);

    // Get invitations for a specific event (owner only)
    Route::get('/events/{event_id}/invitations', [EventInvitationController::class, 'eventInvitations']);

    // Manually trigger sending invitations for an event
    Route::post('/events/{event_id}/invitations/send', [EventInvitationController::class, 'sendInvitations']);

    // Guest (unregistered) email invitations
    Route::post('/events/{event}/guest-invitations/validate-email', [EventGuestInvitationController::class, 'validateEmail'])
        ->whereNumber('event');
    Route::post('/events/{event}/guest-invitations', [EventGuestInvitationController::class, 'store'])
        ->whereNumber('event');

    // ──────────────────────────────────────
    // Global Chat (Premium Users)
    // ──────────────────────────────────────

    Route::get('/chat/users', [ChatController::class, 'getChatUsers']);
    Route::get('/chat/blocks', [ChatController::class, 'listBlocks']);
    Route::get('/chat/can-message/{user_id}', [ChatController::class, 'canMessage'])->whereNumber('user_id');
    Route::post('/chat/block/{user_id}', [ChatController::class, 'blockUser'])->whereNumber('user_id');
    Route::delete('/chat/block/{user_id}', [ChatController::class, 'unblockUser'])->whereNumber('user_id');
    Route::post('/chat/validate-message', [ChatController::class, 'validateMessage'])
        ->middleware('global.chat.ratelimit');
    Route::post('/chat/notify-message', [ChatController::class, 'notifyMessage'])
        ->middleware('global.chat.ratelimit');

    // ──────────────────────────────────────
    // Chat Permissions (Event-based)
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
