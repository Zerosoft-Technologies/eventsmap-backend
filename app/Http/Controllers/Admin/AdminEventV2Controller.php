<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\MediaHelper;
use App\Http\Requests\Admin\EventV2\StoreEventV2Request;
use App\Http\Requests\Admin\EventV2\SyncEventV2TalentsRequest;
use App\Http\Requests\Admin\EventV2\UpdateEventV2Request;
use App\Http\Resources\Admin\AdminEventV2Resource;
use App\Models\EventV2;
use App\Models\GalleryImage;
use App\Services\V2\EventInvitedEntitiesService;
use App\Services\V2\EventService;
use App\Services\V2\RecurringSeriesLifecycleService;
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
        private readonly RecurringSeriesLifecycleService $recurringSeriesLifecycleService,
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
                'venue_name' => $data['venue_name'] ?? null,
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
                'description', 'contact_box_message', 'contact_box_design_message', 'venue_details', 'facebook_url', 'instagram_url',
                'tiktok_url', 'ticket_url', 'booking_instructions', 'event_option',
                'condition_entrance_fee', 'condition_dress_code', 'condition_age_limit',
                'is_recurring', 'is_copy_event', 'show_upcoming_events', 'show_past_events',
                'subcategory_ids', 'invited_talents', 'invited_organisers', 'invited_venues',
                'publish_status',
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
                'start_time', 'end_time', 'address', 'venue_name', 'latitude', 'longitude',
                'dress_code', 'age_limit', 'entrance_status', 'entrance_fee', 'venue_id',
                'contact_phone', 'contact_email', 'contact_website', 'description',
                'contact_box_message', 'contact_box_design_message', 'venue_details', 'facebook_url', 'instagram_url',
                'tiktok_url', 'ticket_url', 'booking_instructions', 'event_option',
                'condition_entrance_fee', 'condition_dress_code', 'condition_age_limit',
                'invited_talents', 'invited_organisers', 'invited_venues',
                'is_recurring', 'is_copy_event', 'show_upcoming_events', 'show_past_events',
                'status', 'publish_status', 'is_approved',
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
            'cancel' => $event->isSeriesInstance()
                ? $this->cancelSeriesOccurrence($event, $admin)
                : $this->eventService->cancel($event, $admin),
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

    /**
     * @param  list<string>  $paths
     * @return list<array{id: string, path: string, url: string}>
     */
    private function mapAdditionalImagePathsToPayload(array $paths): array
    {
        $out = [];
        foreach ($paths as $p) {
            if (is_string($p) && $p !== '') {
                $out[] = [
                    'id' => $this->deriveEventV2ImagePublicId($p),
                    'path' => $p,
                    'url' => MediaHelper::url($p),
                ];
            }
        }

        return $out;
    }

    /**
     * Public id for API clients: gallery `image_id`, else UUID in filename, else full path/URL.
     */
    private function deriveEventV2ImagePublicId(string $image): string
    {
        $image = trim($image);
        if ($image === '') {
            return $image;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $image, $m)) {
            return strtolower($m[0]);
        }

        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $image, $m)) {
            return strtolower($m[0]);
        }

        return $image;
    }

    private function publicIdsMatch(string $stored, string $requestedFromClient): bool
    {
        return $this->deriveEventV2ImagePublicId($stored) === $this->deriveEventV2ImagePublicId($requestedFromClient);
    }

    /**
     * @return array{id: string, url: string|null, caption: string|null}
     */
    private function resolveEventV2StoredImage(EventV2 $event, string $image): array
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $image)) {
            $galleryImage = GalleryImage::where('image_id', $image)
                ->where('user_id', $event->user_id)
                ->where('is_deleted', false)
                ->first();

            if (! $galleryImage) {
                $publicId = $this->deriveEventV2ImagePublicId($image);

                return ['id' => $publicId, 'url' => null, 'caption' => null];
            }

            return [
                'id' => (string) $galleryImage->image_id,
                'url' => MediaHelper::url($galleryImage->file_path),
                'caption' => $galleryImage->caption,
            ];
        }

        $publicId = $this->deriveEventV2ImagePublicId($image);

        return [
            'id' => $publicId,
            'url' => MediaHelper::resolveUrl($image),
            'caption' => null,
        ];
    }

    /**
     * Find media file path on the public disk by UUID filename (without extension).
     */

    private function findMediaPathByUuid(string $uuid): ?string
    {
        $storage = Storage::disk('public');
        $foldersToSearch = ['events', 'media', 'uploads', 'images', 'talents', 'categories'];

        foreach ($foldersToSearch as $folder) {
            if (! $storage->exists($folder)) {
                continue;
            }
            $files = $storage->files($folder);
            foreach ($files as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                if ($filename === $uuid) {
                    return $file;
                }
            }
        }

        foreach ($storage->allFiles() as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            if ($filename === $uuid) {
                return $file;
            }
        }

        return null;
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
     * POST /api/admin/events-v2/{id}/talents
     *
     * Sync event talents and pivot sort order. Body: { "items": [ { "id": talentId, "sort_order": 0 }, ... ] }.
     * An empty items array removes all talents from the event.
     */
    public function syncTalents(SyncEventV2TalentsRequest $request, $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);
        $items = $request->validated('items');

        $sync = collect($items)
            ->keyBy('id')
            ->map(fn (array $row) => [
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ])
            ->all();

        $event->talents()->sync($sync);
        $event->load('talents');

        return response()->json([
            'success' => true,
            'message' => 'Event talents updated successfully',
            'data' => $event->talents,
        ]);
    }

    /**
     * PATCH /api/admin/events-v2/{id}/talents/reorder
     *
     * Update pivot `sort_order` for talents already linked to the event (same as v1 admin).
     */
    public function reorderTalents(Request $request, $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:talents,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        $attached = $event->talents->pluck('id')->all();
        foreach ($validated['items'] as $item) {
            if (! in_array($item['id'], $attached, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more talents are not linked to this event.',
                ], 422);
            }
        }

        foreach ($validated['items'] as $item) {
            $event->talents()->updateExistingPivot($item['id'], [
                'sort_order' => $item['sort_order'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Talents reordered successfully.',
            ],
        ]);
    }

    /**
     * DELETE /api/admin/events-v2/{id}/talents/{talentId}
     *
     * Remove a talent from the event pivot (same as v1 admin).
     */
    public function detachTalent($id, int $talentId): JsonResponse
    {
        $event = EventV2::findOrFail($id);
        $event->talents()->detach($talentId);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Talent detached successfully.',
            ],
        ]);
    }

    /**
     * GET /api/admin/events-v2/{id}/media
     *
     * Cover + additional gallery as a flat list (same shape as v1 admin GET events/{id}/media: id, url, alt_text, caption, is_primary, sort_order) plus `path` for v2.
     */
    public function getMedia($id): JsonResponse
    {
        $event = EventV2::findOrFail($id);
        $items = [];
        $sort = 0;

        if (! empty($event->image_path)) {
            $resolved = $this->resolveEventV2StoredImage($event, (string) $event->image_path);
            if ($resolved['url'] !== null) {
                $items[] = [
                    'id' => $resolved['id'],
                    'path' => $event->image_path,
                    'url' => $resolved['url'],
                    'alt_text' => null,
                    'caption' => $resolved['caption'],
                    'is_primary' => true,
                    'sort_order' => $sort++,
                ];
            }
        }

        if (! empty($event->additional_images) && is_array($event->additional_images)) {
            foreach ($event->additional_images as $image) {
                if (! is_string($image) || $image === '') {
                    continue;
                }
                $resolved = $this->resolveEventV2StoredImage($event, $image);
                if ($resolved['url'] === null) {
                    continue;
                }
                $items[] = [
                    'id' => $resolved['id'],
                    'path' => $image,
                    'url' => $resolved['url'],
                    'alt_text' => null,
                    'caption' => $resolved['caption'],
                    'is_primary' => false,
                    'sort_order' => $sort++,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * POST /api/admin/events-v2/{id}/media
     *
     * Attach a gallery image: resolve by `media_id` (UUID filename on public disk) or by `path`.
     * Appends to `additional_images` (same contract as v1 admin events /media).
     */
    public function attachMedia(Request $request, $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);

        $validated = $request->validate([
            'media_id' => 'nullable|string|uuid',
            'path' => 'required_without:media_id|nullable|string|max:500',
        ]);

        $path = null;

        if (! empty($validated['media_id'])) {
            $path = $this->findMediaPathByUuid($validated['media_id']);
            if (! $path) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'MEDIA_NOT_FOUND',
                        'message' => 'No media file found with the specified media_id.',
                    ],
                ], 404);
            }
        } elseif (! empty($validated['path'])) {
            $path = $validated['path'];
            if (! Storage::disk('public')->exists($path)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FILE_NOT_FOUND',
                        'message' => 'The specified file does not exist in storage.',
                    ],
                ], 404);
            }
        } else {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_REQUEST',
                    'message' => 'Either media_id or path must be provided.',
                ],
            ], 422);
        }

        $current = is_array($event->additional_images) ? $event->additional_images : [];
        if (in_array($path, $current, true)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Media already attached.',
                    'media' => [
                        'id' => $this->deriveEventV2ImagePublicId($path),
                        'path' => $path,
                        'url' => MediaHelper::url($path),
                    ],
                    'additional_images' => $this->mapAdditionalImagePathsToPayload($current),
                ],
            ]);
        }

        $current[] = $path;
        $event->additional_images = array_values($current);
        $event->save();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Media attached successfully.',
                'media' => [
                    'id' => $this->deriveEventV2ImagePublicId($path),
                    'path' => $path,
                    'url' => MediaHelper::url($path),
                ],
                'additional_images' => $this->mapAdditionalImagePathsToPayload($event->additional_images),
            ],
        ]);
    }

    /**
     * DELETE /api/admin/events-v2/{id}/media/{mediaId}
     *
     * Remove cover or a gallery item. `mediaId` is the same public `id` returned by GET
     * (UUID, path-derived UUID, or full URL/path string).
     */
    public function detachMedia($id, string $mediaId): JsonResponse
    {
        $event = EventV2::findOrFail($id);
        if (trim($mediaId) === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MEDIA_NOT_FOUND',
                    'message' => 'No image matched this id for the event.',
                ],
            ], 404);
        }

        if (! empty($event->image_path) && $this->publicIdsMatch((string) $event->image_path, $mediaId)) {
            $this->deleteImage((string) $event->image_path);
            $event->image_path = null;
            $event->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Media removed successfully.',
                ],
            ]);
        }

        if (! empty($event->additional_images) && is_array($event->additional_images)) {
            $kept = [];
            $removed = false;
            foreach ($event->additional_images as $p) {
                if (! is_string($p) || $p === '') {
                    continue;
                }
                if ($this->publicIdsMatch($p, $mediaId)) {
                    $this->deleteImage($p);
                    $removed = true;
                } else {
                    $kept[] = $p;
                }
            }
            if ($removed) {
                $event->additional_images = array_values($kept);
                $event->save();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => 'Media removed successfully.',
                    ],
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'MEDIA_NOT_FOUND',
                'message' => 'No image matched this id for the event.',
            ],
        ], 404);
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

    /**
     * POST /api/admin/events-v2/{id}/cancel-occurrence
     *
     * Cancel or remove a recurring series occurrence (with invitee notifications when cancelled).
     */
    public function cancelOccurrence(Request $request, int $id): JsonResponse
    {
        $request->validate(['confirm' => 'required|accepted']);

        $event = EventV2::findOrFail($id);

        if (! $event->isSeriesInstance()) {
            return response()->json([
                'success' => false,
                'message' => 'This event is not a recurring series occurrence.',
            ], 422);
        }

        $lifecycle = $this->recurringSeriesLifecycleService->cancelOccurrence(
            $event,
            $request->user(),
            'admin_occurrence_cancel',
        );

        $event->refresh();
        $this->eventInvitedEntitiesService->hydrate([$event]);

        return response()->json([
            'success' => true,
            'message' => 'Event occurrence processed successfully',
            'data' => new AdminEventV2Resource($event),
            'lifecycle' => $lifecycle,
        ]);
    }

    private function cancelSeriesOccurrence(EventV2 $event, $admin): EventV2
    {
        $this->recurringSeriesLifecycleService->cancelOccurrence($event, $admin, 'admin_occurrence_cancel');

        return $event->fresh(['category', 'subcategories', 'venue', 'organisers', 'talents', 'user']);
    }
}
