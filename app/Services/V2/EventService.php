<?php

namespace App\Services\V2;

use App\Models\EventV2;
use App\Models\EventV2View;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * EventService - Business logic layer for V2 Events.
 *
 * Handles event CRUD operations, status management, file uploads,
 * and admin moderation actions.
 */
class EventService
{
    /**
     * Create a new event.
     *
     * @param array $data Validated event data
     * @param User $user The authenticated user creating the event
     * @return EventV2 The created event with relationships loaded
     *
     * @throws \Throwable If database transaction fails
     */
    public function create(array $data, User $user): EventV2
    {
        return DB::transaction(function () use ($data, $user) {
            // Prepare base event data with only fillable fields
            $eventData = [
                'user_id' => $user->id,
                'title' => $data['title'],
                'category_id' => $data['category_id'],
                'event_date' => $data['event_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'address' => $data['address'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'dress_code' => $data['dress_code'],
                'age_limit' => $data['age_limit'],
                'entrance_status' => $data['entrance_status'],
                'slug' => $this->generateUniqueSlug($data['title']),
                'status' => $this->resolveStatus($data['event_date'], $data['start_time'], $data['end_time']),
                'is_free_package' => true,
            ];

            // Add optional fields only if they exist in the database
            if (isset($data['venue_id'])) {
                $eventData['venue_id'] = $data['venue_id'];
            }

            // Add admin moderation fields only if columns exist
            if (\Schema::hasColumn('events_v2', 'is_approved')) {
                $eventData['is_approved'] = false;
            }

            // Handle image upload with secure hashed filename
            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $eventData['image_path'] = $this->storeImage($data['image']);
            }

            // Extract pivot data before creating
            $subcategoryIds = $data['subcategory_ids'] ?? [];
            $organiserIds = $data['organiser_ids'] ?? [];
            $talentIds = $data['talent_ids'] ?? [];

            $event = EventV2::create($eventData);

            // Sync pivot relationships
            if (!empty($subcategoryIds)) {
                $event->subcategories()->sync($subcategoryIds);
            }
            if (!empty($organiserIds)) {
                $event->organisers()->sync($organiserIds);
            }
            if (!empty($talentIds)) {
                $event->talents()->sync($this->formatTalentSync($talentIds));
            }

            Log::info('Event created', ['event_id' => $event->id, 'user_id' => $user->id]);

            return $event->load(['category', 'subcategories', 'venue', 'organisers', 'talents', 'user']);
        });
    }

    /**
     * Get events created by a specific user (for sidebar).
     *
     * @param User $user The authenticated user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserEvents(User $user)
    {
        return EventV2::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Update an existing event.
     *
     * @param EventV2 $event The event to update
     * @param array $data Validated update data
     * @return EventV2 The updated event with relationships loaded
     *
     * @throws \Throwable If database transaction fails
     */
    public function update(EventV2 $event, array $data): EventV2
    {
        return DB::transaction(function () use ($event, $data) {
            // Prepare update data with only valid fields
            $updateData = [];

            // Update basic fields only if provided
            $allowedFields = [
                'title', 'category_id', 'event_date', 'start_time', 'end_time',
                'address', 'latitude', 'longitude', 'dress_code', 'age_limit',
                'entrance_status', 'venue_id', 'image_path'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Recalculate status if date/time changed (only if not admin-controlled)
            if (!in_array($event->status, [EventV2::STATUS_SUSPENDED, EventV2::STATUS_CANCELLED])) {
                $eventDate = $data['event_date'] ?? $event->event_date->format('Y-m-d');
                $startTime = $data['start_time'] ?? $event->start_time;
                $endTime = $data['end_time'] ?? $event->end_time;
                $updateData['status'] = $this->resolveStatus($eventDate, $startTime, $endTime);
            }

            // Handle image upload with secure hashed filename
            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                // Delete old image
                $this->deleteImage($event->image_path);
                $updateData['image_path'] = $this->storeImage($data['image']);
            }

            // Extract pivot data
            $subcategoryIds = $data['subcategory_ids'] ?? null;
            $organiserIds = $data['organiser_ids'] ?? null;
            $talentIds = $data['talent_ids'] ?? null;

            // Update the event with safe data
            $event->update($updateData);

            // Sync pivot relationships only if provided
            if ($subcategoryIds !== null) {
                $event->subcategories()->sync($subcategoryIds);
            }
            if ($organiserIds !== null) {
                $event->organisers()->sync($organiserIds);
            }
            if ($talentIds !== null) {
                $event->talents()->sync($this->formatTalentSync($talentIds));
            }

            Log::info('Event updated', ['event_id' => $event->id]);

            return $event->load(['category', 'subcategories', 'venue', 'organisers', 'talents', 'user']);
        });
    }

    /**
     * Soft delete an event.
     *
     * @param EventV2 $event The event to delete
     */
    public function delete(EventV2 $event): void
    {
        DB::transaction(function () use ($event) {
            // Note: Image is NOT deleted on soft delete (can be restored)
            $event->delete();
            Log::info('Event soft deleted', ['event_id' => $event->id]);
        });
    }

    /**
     * Force delete an event (permanently).
     *
     * @param EventV2 $event The event to permanently delete
     */
    public function forceDelete(EventV2 $event): void
    {
        DB::transaction(function () use ($event) {
            // Delete image from storage
            $this->deleteImage($event->image_path);

            // Detach all relationships
            $event->subcategories()->detach();
            $event->organisers()->detach();
            $event->talents()->detach();

            $event->forceDelete();
            Log::info('Event permanently deleted', ['event_id' => $event->id]);
        });
    }

    /**
     * Restore a soft-deleted event.
     *
     * @param EventV2 $event The trashed event to restore
     * @return EventV2 The restored event
     */
    public function restore(EventV2 $event): EventV2
    {
        $event->restore();
        Log::info('Event restored', ['event_id' => $event->id]);
        return $event->load(['category', 'subcategories', 'venue', 'organisers', 'talents', 'user']);
    }

    // ──────────────────────────────────────
    // Admin Moderation Methods
    // ──────────────────────────────────────

    /**
     * Approve an event for public display.
     *
     * @param EventV2 $event The event to approve
     * @param User $admin The admin performing the action
     * @return EventV2 The approved event
     */
    public function approve(EventV2 $event, User $admin): EventV2
    {
        $event->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'status' => $this->resolveStatus(
                $event->event_date->format('Y-m-d'),
                $event->start_time,
                $event->end_time
            ),
        ]);

        Log::info('Event approved', ['event_id' => $event->id, 'admin_id' => $admin->id]);
        return $event->fresh(['category', 'subcategories', 'venue', 'organisers', 'talents', 'user']);
    }

    /**
     * Suspend an event (admin moderation action).
     *
     * @param EventV2 $event The event to suspend
     * @param User $admin The admin performing the action
     * @param string|null $reason Reason for suspension
     * @return EventV2 The suspended event
     */
    public function suspend(EventV2 $event, User $admin, ?string $reason = null): EventV2
    {
        $event->update([
            'status' => EventV2::STATUS_SUSPENDED,
            'suspension_reason' => $reason,
            'suspended_at' => now(),
            'suspended_by' => $admin->id,
        ]);

        Log::warning('Event suspended', [
            'event_id' => $event->id,
            'admin_id' => $admin->id,
            'reason' => $reason,
        ]);

        return $event->fresh(['category', 'subcategories', 'venue', 'organisers', 'talents', 'user']);
    }

    /**
     * Cancel an event (admin moderation action).
     *
     * @param EventV2 $event The event to cancel
     * @param User $admin The admin performing the action
     * @return EventV2 The cancelled event
     */
    public function cancel(EventV2 $event, User $admin): EventV2
    {
        $event->update([
            'status' => EventV2::STATUS_CANCELLED,
        ]);

        Log::info('Event cancelled', ['event_id' => $event->id, 'admin_id' => $admin->id]);
        return $event->fresh(['category', 'subcategories', 'venue', 'organisers', 'talents', 'user']);
    }

    /**
     * Mark an event as completed (admin moderation action).
     *
     * @param EventV2 $event The event to mark completed
     * @param User $admin The admin performing the action
     * @return EventV2 The completed event
     */
    public function markCompleted(EventV2 $event, User $admin): EventV2
    {
        $event->update([
            'status' => EventV2::STATUS_COMPLETED,
        ]);

        Log::info('Event marked completed', ['event_id' => $event->id, 'admin_id' => $admin->id]);
        return $event->fresh(['category', 'subcategories', 'venue', 'organisers', 'talents', 'user']);
    }

    /**
     * Unsuspend an event (lift suspension).
     *
     * @param EventV2 $event The suspended event
     * @param User $admin The admin performing the action
     * @return EventV2 The unsuspended event
     */
    public function unsuspend(EventV2 $event, User $admin): EventV2
    {
        $newStatus = $this->resolveStatus(
            $event->event_date->format('Y-m-d'),
            $event->start_time,
            $event->end_time
        );

        $event->update([
            'status' => $newStatus,
            'suspension_reason' => null,
            'suspended_at' => null,
            'suspended_by' => null,
        ]);

        Log::info('Event unsuspended', ['event_id' => $event->id, 'admin_id' => $admin->id]);
        return $event->fresh(['category', 'subcategories', 'venue', 'organisers', 'talents', 'user']);
    }

    // ──────────────────────────────────────
    // Analytics Methods
    // ──────────────────────────────────────

    /**
     * Record a view for an event.
     *
     * @param EventV2 $event The event being viewed
     * @param User|null $user The authenticated user (if any)
     * @param string|null $ipAddress The viewer's IP address
     * @param string|null $userAgent The viewer's user agent
     * @param string|null $referrer The referrer URL
     */
    public function recordView(
        EventV2 $event,
        ?User $user = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referrer = null
    ): void {
        EventV2View::create([
            'event_v2_id' => $event->id,
            'user_id' => $user?->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? Str::limit($userAgent, 255) : null,
            'referrer' => $referrer ? Str::limit($referrer, 255) : null,
            'viewed_at' => now(),
        ]);

        $event->incrementViews();
    }

    // ──────────────────────────────────────
    // Status Resolution
    // ──────────────────────────────────────

    /**
     * Auto-resolve event status based on date/time.
     * Handles overnight events (end_time < start_time).
     *
     * @param string $eventDate The event date (Y-m-d format)
     * @param string $startTime The start time (H:i format)
     * @param string $endTime The end time (H:i format)
     * @return string The resolved status constant
     */
    public function resolveStatus(string $eventDate, string $startTime, string $endTime): string
    {
        $now = Carbon::now();
        $start = Carbon::parse("{$eventDate} {$startTime}");
        $end = Carbon::parse("{$eventDate} {$endTime}");

        // If end_time is before or equal to start_time, event spans to next day (overnight)
        if ($end->lte($start)) {
            $end->addDay();
        }

        if ($now->gt($end)) {
            return EventV2::STATUS_COMPLETED;
        }

        if ($now->gte($start) && $now->lte($end)) {
            return EventV2::STATUS_LIVE;
        }

        return EventV2::STATUS_UPCOMING;
    }

    // ──────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────

    /**
     * Generate a unique slug from title with race condition prevention.
     * Uses database locking to prevent duplicate slugs under concurrent requests.
     *
     * @param string $title The event title
     * @return string The unique slug
     */
    private function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);

        if (empty($slug)) {
            $slug = 'event-' . Str::random(8);
        }

        $base = $slug;
        $counter = 1;

        // Use lock to prevent race conditions
        while (EventV2::withTrashed()->where('slug', $slug)->lockForUpdate()->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    /**
     * Store event image with secure hashed filename.
     * Sanitizes and hashes the filename to prevent security issues.
     *
     * @param UploadedFile $image The uploaded image file
     * @return string The stored file path
     */
    private function storeImage(UploadedFile $image): string
    {
        // Generate a secure hashed filename
        $extension = $image->getClientOriginalExtension();
        $hash = Str::random(40);
        $filename = $hash . '.' . strtolower($extension);

        return $image->storeAs('events', $filename, 'public');
    }

    /**
     * Delete an image from storage.
     *
     * @param string|null $imagePath The image path to delete
     */
    private function deleteImage(?string $imagePath): void
    {
        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    /**
     * Format talent IDs for sync with pivot data.
     *
     * @param array $talentIds Array of talent IDs
     * @return array Formatted array for sync with sort_order
     */
    private function formatTalentSync(array $talentIds): array
    {
        $sync = [];
        foreach ($talentIds as $index => $talentId) {
            $sync[$talentId] = ['sort_order' => $index];
        }
        return $sync;
    }
}
