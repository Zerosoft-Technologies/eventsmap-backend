<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventV2\StoreEventV2Request;
use App\Http\Requests\Admin\EventV2\UpdateEventV2Request;
use App\Http\Resources\Admin\AdminEventV2Resource;
use App\Models\EventV2;
use App\Services\V2\EventInvitedEntitiesService;
use App\Services\V2\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AdminEventV2Controller - Full Admin CRUD for V2 Events.
 *
 * Provides full CRUD, approval/unapproval, suspension,
 * bulk operations, trash management, and statistics.
 * Requires admin authentication.
 */
class AdminEventV2Controller extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventInvitedEntitiesService $eventInvitedEntitiesService,
    ) {}

    // ──────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────

    /**
     * GET /api/admin/events-v2
     *
     * List all events with admin filters (including pending approval).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,upcoming,live,completed,cancelled,suspended',
            'is_approved' => 'nullable|boolean',
            'user_id' => 'nullable|integer|exists:users,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort' => 'nullable|string|in:event_date,created_at,title,view_count',
            'order' => 'nullable|string|in:asc,desc',
        ]);

        $query = EventV2::query()
            ->with(['category', 'venue', 'user']);

        // Search filter (title, address, slug, user email)
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->input('search');
            $q->where(function ($query) use ($search) {
                $query->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('address', 'ILIKE', "%{$search}%")
                    ->orWhere('slug', 'ILIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'ILIKE', "%{$search}%")
                            ->orWhere('name', 'ILIKE', "%{$search}%");
                    });
            });
        });

        // Status filter
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->input('status'));
        });

        // Approval filter
        $query->when($request->has('is_approved'), function ($q) use ($request) {
            $q->where('is_approved', $request->boolean('is_approved'));
        });

        // User filter
        $query->when($request->filled('user_id'), function ($q) use ($request) {
            $q->where('user_id', $request->input('user_id'));
        });

        // Category filter
        $query->when($request->filled('category_id'), function ($q) use ($request) {
            $q->where('category_id', $request->input('category_id'));
        });

        // Date range filter
        $query->when($request->filled('date_from'), function ($q) use ($request) {
            $q->where('event_date', '>=', $request->input('date_from'));
        });
        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('event_date', '<=', $request->input('date_to'));
        });

        // Sorting
        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        // Pagination
        $perPage = $request->input('per_page', 20);
        $events = $query->paginate($perPage);

        $this->eventInvitedEntitiesService->hydrate($events->items());

        return response()->json([
            'success' => true,
            'message' => 'Events fetched successfully',
            'data' => [
                'events' => AdminEventV2Resource::collection($events->items()),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/events-v2/{id}
     *
     * Show a single event with all relationships.
     */
    public function show($id): JsonResponse
    {
        $event = EventV2::with(['category', 'venue', 'user', 'organisers', 'talents'])
            ->findOrFail($id);

        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => 'Event fetched successfully',
            'data' => new AdminEventV2Resource($event),
        ]);
    }

    /**
     * POST /api/admin/events-v2
     *
     * Create a new event as admin.
     */
    public function store(StoreEventV2Request $request): JsonResponse
    {
        $data = $request->validated();

        $event = DB::transaction(function () use ($data, $request) {
            $eventType = $data['event_type'] ?? 'free';
            $isFreePackage = $eventType === 'free';

            $eventData = [
                'user_id' => $data['user_id'],
                'title' => $data['title'],
                'event_type' => $eventType,
                'category_id' => $data['category_id'],
                'event_date' => $data['start_date'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'start_datetime' => $data['start_datetime'],
                'end_datetime' => $data['end_datetime'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'address' => $data['address'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'dress_code' => $data['dress_code'] ?? null,
                'age_limit' => $data['age_limit'] ?? null,
                'entrance_status' => $data['entrance_status'] ?? null,
                'slug' => $this->generateUniqueSlug($data['title']),
                'status' => $data['status'] ?? $this->eventService->resolveStatus($data['start_datetime'], $data['end_datetime']),
                'is_free_package' => $isFreePackage,
                'is_approved' => $data['is_approved'] ?? false,
            ];

            $optionalFields = [
                'venue_id', 'entrance_fee', 'contact_phone', 'contact_email', 'contact_website',
                'description', 'contact_box_message', 'venue_details', 'facebook_url', 'instagram_url',
                'tiktok_url', 'ticket_url', 'booking_instructions', 'event_option',
                'condition_entrance_fee', 'condition_dress_code', 'condition_age_limit',
                'is_recurring', 'is_copy_event', 'show_upcoming_events', 'show_past_events',
                'subcategory_ids', 'invited_talents', 'invited_organisers', 'invited_venues',
            ];

            foreach ($optionalFields as $field) {
                if (array_key_exists($field, $data)) {
                    $eventData[$field] = $data[$field];
                }
            }

            // Handle main image
            if (isset($data['image_path'])) {
                if ($data['image_path'] instanceof UploadedFile) {
                    $eventData['image_path'] = $this->storeImage($data['image_path']);
                } elseif (is_string($data['image_path'])) {
                    $eventData['image_path'] = $data['image_path'];
                }
            }

            // Handle additional images
            if (! empty($data['additional_images']) && is_array($data['additional_images'])) {
                $paths = [];
                foreach ($data['additional_images'] as $imageRef) {
                    if ($imageRef instanceof UploadedFile) {
                        $paths[] = $this->storeImage($imageRef);
                    } elseif (is_string($imageRef) && $imageRef !== '') {
                        $paths[] = $imageRef;
                    }
                }
                if (! empty($paths)) {
                    $eventData['additional_images'] = $paths;
                }
            }

            // Set approved_at/approved_by if creating as approved
            if (! empty($eventData['is_approved'])) {
                $eventData['approved_at'] = now();
                $eventData['approved_by'] = $request->user()->id;
            }

            $event = EventV2::create($eventData);

            Log::info('Admin created event', ['event_id' => $event->id, 'admin_id' => $request->user()->id]);

            return $event->load(['category', 'venue', 'user']);
        });

        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => new AdminEventV2Resource($event),
        ], 201);
    }

    /**
     * PUT /api/admin/events-v2/{id}
     *
     * Update an existing event.
     */
    public function update(UpdateEventV2Request $request, $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);
        $data = $request->validated();

        $event = DB::transaction(function () use ($event, $data, $request) {
            $allowedFields = [
                'user_id', 'title', 'event_type', 'category_id', 'subcategory_ids',
                'event_date', 'start_date', 'end_date', 'start_datetime', 'end_datetime',
                'start_time', 'end_time', 'address', 'latitude', 'longitude',
                'dress_code', 'age_limit', 'entrance_status', 'entrance_fee', 'venue_id',
                'contact_phone', 'contact_email', 'contact_website', 'description',
                'contact_box_message', 'venue_details', 'facebook_url', 'instagram_url',
                'tiktok_url', 'ticket_url', 'booking_instructions', 'event_option',
                'condition_entrance_fee', 'condition_dress_code', 'condition_age_limit',
                'invited_talents', 'invited_organisers', 'invited_venues',
                'is_recurring', 'is_copy_event', 'show_upcoming_events', 'show_past_events',
                'status', 'is_approved',
            ];

            $updateData = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Backward compat: event_date from start_date
            if (array_key_exists('start_date', $data) && ! array_key_exists('event_date', $data)) {
                $updateData['event_date'] = $data['start_date'];
            }

            if (array_key_exists('event_type', $data)) {
                $updateData['is_free_package'] = $data['event_type'] === 'free';
            }

            // Handle main image
            if ($request->hasFile('image_path')) {
                $this->deleteImage($event->image_path);
                $updateData['image_path'] = $this->storeImage($request->file('image_path'));
            } elseif (isset($data['image_path']) && is_string($data['image_path'])) {
                $updateData['image_path'] = $data['image_path'];
            }

            // Handle additional images
            if ($request->hasFile('additional_images')) {
                $this->deleteAllAdditionalImages($event);
                $newImages = [];
                foreach ($request->file('additional_images') as $image) {
                    $newImages[] = $this->storeImage($image);
                }
                $updateData['additional_images'] = $newImages;
            } elseif (array_key_exists('additional_images', $data) && is_array($data['additional_images'])) {
                $this->deleteAllAdditionalImages($event);
                $updateData['additional_images'] = array_values(array_filter($data['additional_images'], fn ($v) => ! empty($v)));
            }

            $event->update($updateData);

            Log::info('Admin updated event', ['event_id' => $event->id, 'admin_id' => $request->user()->id]);

            return $event->load(['category', 'venue', 'user']);
        });

        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => new AdminEventV2Resource($event),
        ]);
    }

    /**
     * DELETE /api/admin/events-v2/{id}
     *
     * Soft delete an event.
     */
    public function destroy($id): JsonResponse
    {
        $event = EventV2::findOrFail($id);

        $this->eventService->delete($event);

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully',
        ]);
    }

    // ──────────────────────────────────────
    // Approve / Unapprove
    // ──────────────────────────────────────

    /**
     * POST /api/admin/events-v2/{id}/approve
     *
     * Approve an event for public display.
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);
        $admin = $request->user();

        $event = $this->eventService->approve($event, $admin);

        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => 'Event approved successfully',
            'data' => new AdminEventV2Resource($event),
        ]);
    }

    /**
     * POST /api/admin/events-v2/{id}/unapprove
     *
     * Unapprove an event (remove from public display).
     */
    public function unapprove($id): JsonResponse
    {
        $event = EventV2::findOrFail($id);

        $event->update([
            'is_approved' => false,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        Log::info('Admin unapproved event', ['event_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Event unapproved successfully',
            'data' => new AdminEventV2Resource($event->load(['category', 'venue', 'user'])),
        ]);
    }

    // ──────────────────────────────────────
    // Suspend / Unsuspend (EventV2 only)
    // ──────────────────────────────────────

    /**
     * POST /api/admin/events-v2/{id}/suspend
     *
     * Suspend an event. Requires suspension_reason.
     * Cannot suspend a cancelled event.
     */
    public function suspend(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $event = EventV2::findOrFail($id);

        if ($event->status === EventV2::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot suspend a cancelled event',
            ], 422);
        }

        $event = $this->eventService->suspend($event, $request->user(), $request->input('reason'));

        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => 'Event suspended successfully',
            'data' => new AdminEventV2Resource($event),
        ]);
    }

    /**
     * POST /api/admin/events-v2/{id}/unsuspend
     *
     * Unsuspend an event. Cannot unsuspend a cancelled event.
     * Status reverts to computed value (upcoming/live/completed).
     */
    public function unsuspend(Request $request, $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);

        if ($event->status !== EventV2::STATUS_SUSPENDED) {
            return response()->json([
                'success' => false,
                'message' => 'Event is not currently suspended',
            ], 422);
        }

        $event = $this->eventService->unsuspend($event, $request->user());

        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => 'Event unsuspended successfully',
            'data' => new AdminEventV2Resource($event),
        ]);
    }

    // ──────────────────────────────────────
    // Bulk Operations
    // ──────────────────────────────────────

    /**
     * POST /api/admin/events-v2/bulk-approve
     *
     * Approve multiple events by IDs.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:events_v2,id',
        ]);

        $admin = $request->user();
        $count = EventV2::whereIn('id', $request->input('ids'))
            ->update([
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]);

        Log::info('Admin bulk-approved events', ['count' => $count, 'admin_id' => $admin->id]);

        return response()->json([
            'success' => true,
            'message' => "{$count} events approved successfully",
        ]);
    }

    /**
     * POST /api/admin/events-v2/bulk-delete
     *
     * Soft delete multiple events by IDs.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:events_v2,id',
        ]);

        $count = EventV2::whereIn('id', $request->input('ids'))->count();
        EventV2::whereIn('id', $request->input('ids'))->delete();

        Log::info('Admin bulk-deleted events', ['count' => $count, 'admin_id' => $request->user()->id]);

        return response()->json([
            'success' => true,
            'message' => "{$count} events deleted successfully",
        ]);
    }

    // ──────────────────────────────────────
    // Existing Endpoints (preserved)
    // ──────────────────────────────────────

    /**
     * GET /api/admin/events-v2/trashed
     *
     * List soft-deleted events.
     */
    public function trashed(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 20);
        $events = EventV2::onlyTrashed()
            ->with(['category', 'user'])
            ->orderBy('deleted_at', 'desc')
            ->paginate($perPage);

        $this->eventInvitedEntitiesService->hydrate($events->items());

        return response()->json([
            'success' => true,
            'message' => 'Trashed events fetched successfully',
            'data' => [
                'events' => AdminEventV2Resource::collection($events->items()),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
            ],
        ]);
    }

    /**
     * PATCH /api/admin/events-v2/{id}/restore
     *
     * Restore a soft-deleted event.
     */
    public function restore($id): JsonResponse
    {
        $event = EventV2::onlyTrashed()->findOrFail($id);

        $event = $this->eventService->restore($event);

        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => 'Event restored successfully',
            'data' => new AdminEventV2Resource($event),
        ]);
    }

    /**
     * DELETE /api/admin/events-v2/{id}/force
     *
     * Permanently delete an event.
     */
    public function forceDelete($id): JsonResponse
    {
        $event = EventV2::withTrashed()->findOrFail($id);

        $this->eventService->forceDelete($event);

        return response()->json([
            'success' => true,
            'message' => 'Event permanently deleted',
        ]);
    }

    /**
     * PATCH /api/admin/events-v2/{id}/status
     *
     * Update event status (legacy moderation endpoint).
     * Actions: approve, suspend, cancel, complete, unsuspend
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:approve,suspend,cancel,complete,unsuspend',
            'reason' => 'nullable|string|max:1000',
        ]);

        $event = EventV2::findOrFail($id);
        $action = $request->input('action');
        $admin = $request->user();

        $event = match ($action) {
            'approve' => $this->eventService->approve($event, $admin),
            'suspend' => $this->eventService->suspend($event, $admin, $request->input('reason')),
            'cancel' => $this->eventService->cancel($event, $admin),
            'complete' => $this->eventService->markCompleted($event, $admin),
            'unsuspend' => $this->eventService->unsuspend($event, $admin),
        };

        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => "Event {$action}d successfully",
            'data' => new AdminEventV2Resource($event),
        ]);
    }

    /**
     * GET /api/admin/events-v2/pending
     *
     * List events pending approval.
     */
    public function pending(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 20);
        $events = EventV2::where('is_approved', false)
            ->whereNotIn('status', [EventV2::STATUS_CANCELLED, EventV2::STATUS_SUSPENDED])
            ->with(['category', 'user'])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        $this->eventInvitedEntitiesService->hydrate($events->items());

        return response()->json([
            'success' => true,
            'message' => 'Pending events fetched successfully',
            'data' => [
                'events' => AdminEventV2Resource::collection($events->items()),
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/events-v2/stats
     *
     * Get event statistics for admin dashboard.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => EventV2::count(),
            'pending_approval' => EventV2::where('is_approved', false)
                ->whereNotIn('status', [EventV2::STATUS_CANCELLED, EventV2::STATUS_SUSPENDED])
                ->count(),
            'approved' => EventV2::where('is_approved', true)->count(),
            'suspended' => EventV2::where('status', EventV2::STATUS_SUSPENDED)->count(),
            'cancelled' => EventV2::where('status', EventV2::STATUS_CANCELLED)->count(),
            'trashed' => EventV2::onlyTrashed()->count(),
            'by_status' => [
                'draft' => EventV2::where('status', EventV2::STATUS_DRAFT)->count(),
                'upcoming' => EventV2::where('status', EventV2::STATUS_UPCOMING)->count(),
                'live' => EventV2::where('status', EventV2::STATUS_LIVE)->count(),
                'completed' => EventV2::where('status', EventV2::STATUS_COMPLETED)->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Event stats fetched successfully',
            'data' => $stats,
        ]);
    }

    // ──────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────

    /**
     * Generate a unique slug from title.
     */
    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        if (empty($slug)) {
            $slug = 'event-'.Str::random(8);
        }
        $base = $slug;
        $counter = 1;
        while (EventV2::withTrashed()->where('slug', $slug)->lockForUpdate()->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * Store an uploaded image to public disk.
     */
    private function storeImage(UploadedFile $image): string
    {
        $extension = $image->getClientOriginalExtension();
        $filename = Str::random(40).'.'.strtolower($extension);
        $path = $image->storeAs('events', $filename, 'public');
        if (! $path) {
            throw new \Exception('Failed to store image file');
        }

        return $path;
    }

    /**
     * Delete an image from storage (skip UUID references).
     */
    private function deleteImage(?string $imagePath): void
    {
        if (! $imagePath) {
            return;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $imagePath)) {
            return;
        }
        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    /**
     * Delete all additional images for an event.
     */
    private function deleteAllAdditionalImages(EventV2 $event): void
    {
        if (! empty($event->additional_images) && is_array($event->additional_images)) {
            foreach ($event->additional_images as $imagePath) {
                if (is_string($imagePath)) {
                    $this->deleteImage($imagePath);
                }
            }
        }
    }

    // ──────────────────────────────────────
    // Invited Entities Relationships
    // ──────────────────────────────────────

    /**
     * GET /api/admin/events-v2/{id}/talents
     *
     * Get invited talents for an event.
     */
    public function talents($id): JsonResponse
    {
        $event = EventV2::findOrFail($id);
        $talents = $event->talents;

        return response()->json([
            'success' => true,
            'message' => 'Event talents fetched successfully',
            'data' => $talents,
        ]);
    }

    /**
     * GET /api/admin/events-v2/{id}/organisers
     *
     * Get invited organisers for an event.
     */
    public function organisers($id): JsonResponse
    {
        $event = EventV2::findOrFail($id);
        $organisers = $event->organisers;

        return response()->json([
            'success' => true,
            'message' => 'Event organisers fetched successfully',
            'data' => $organisers,
        ]);
    }

    /**
     * GET /api/admin/events-v2/{id}/venues
     *
     * Get invited venues for an event.
     */
    public function venues($id): JsonResponse
    {
        $event = EventV2::findOrFail($id);
        $venues = $event->venues;

        return response()->json([
            'success' => true,
            'message' => 'Event venues fetched successfully',
            'data' => $venues,
        ]);
    }
}
