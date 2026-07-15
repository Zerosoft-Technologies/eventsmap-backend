<?php

namespace App\Services\V2;

use App\Helpers\MediaHelper;
use App\Models\GalleryImage;
use App\Models\EventV2;
use App\Models\User;
use App\Support\PublishStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Manages individual recurring event occurrences without touching RecurringSeries.
 */
class IndividualInstanceService
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly RecurringSeriesLifecycleService $lifecycleService,
        private readonly EventInstanceUpdateNotificationService $updateNotificationService,
    ) {}

    /**
     * View a single series occurrence (same payload shape as event edit + occurrence meta).
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function show(EventV2 $event, User $user): array
    {
        $this->assertSeriesOccurrence($event);
        $this->assertOwner($event, $user);

        $event->load(['category', 'venue', 'organisers', 'talents', 'user', 'recurringSeries']);
        $event->setRelation('subcategories', $event->subcategories_from_ids);

        $subcategoryIds = $event->subcategory_ids ?? $event->subcategories->pluck('id')->all();

        $payload = [
            'id' => $event->id,
            'title' => $event->title,
            'event_type' => $event->event_type ?? 'free',
            'category_id' => $event->category_id,
            'subcategory_ids' => $subcategoryIds,
            'start_date' => $event->start_date?->format('Y-m-d'),
            'end_date' => $event->end_date?->format('Y-m-d'),
            'event_date' => $event->event_date?->format('Y-m-d'),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'start_datetime' => $event->start_datetime?->toIso8601String(),
            'end_datetime' => $event->end_datetime?->toIso8601String(),
            'address' => $event->address,
            'venue_name' => $event->venue_name,
            'latitude' => $event->latitude !== null ? (float) $event->latitude : null,
            'longitude' => $event->longitude !== null ? (float) $event->longitude : null,
            'dress_code' => $event->dress_code,
            'age_limit' => $event->age_limit,
            'entrance_status' => $event->entrance_status,
            'publish_status' => $event->publish_status ?? PublishStatus::DRAFT,
            'publish_status_label' => PublishStatus::labels()[$event->publish_status ?? PublishStatus::DRAFT]
                ?? ($event->publish_status ?? PublishStatus::DRAFT),
            'contact_phone' => $event->contact_phone,
            'contact_email' => $event->contact_email,
            'description' => $event->description ?? null,
            'contact_website' => $event->contact_website,
            'contact_box_message' => $event->contact_box_message,
            'contact_box_design_message' => $event->contact_box_design_message,
            'facebook_url' => $event->facebook_url,
            'instagram_url' => $event->instagram_url,
            'tiktok_url' => $event->tiktok_url,
            'ticket_url' => $event->ticket_url,
            'booking_instructions' => $event->booking_instructions,
            'is_recurring' => (bool) ($event->is_recurring ?? false),
            'is_copy_event' => (bool) ($event->is_copy_event ?? false),
            'series_id' => $event->series_id,
            'is_modified' => (bool) $event->is_modified,
            'is_series_instance' => $event->isSeriesInstance(),
            'recurring_series' => $event->recurringSeries ? [
                'id' => $event->recurringSeries->id,
                'recurrence_type' => $event->recurringSeries->recurrence_type,
                'timezone' => $event->recurringSeries->timezone,
                'start_date' => $event->recurringSeries->start_date?->format('Y-m-d'),
                'end_date' => $event->recurringSeries->end_date?->format('Y-m-d'),
            ] : null,
            'show_upcoming_events' => (bool) ($event->show_upcoming_events ?? false),
            'show_past_events' => (bool) ($event->show_past_events ?? false),
            'show_photo_map_marker' => (bool) ($event->show_photo_map_marker ?? false),
            'invited_talents' => is_array($event->invited_talents) ? $event->invited_talents : [],
            'invited_organisers' => is_array($event->invited_organisers) ? $event->invited_organisers : [],
            'invited_venues' => is_array($event->invited_venues) ? $event->invited_venues : [],
            'organiser_ids' => $event->organisers->pluck('id')->values()->all(),
            'talent_ids' => $event->talents->pluck('id')->values()->all(),
            'image_path' => $event->image_path,
        ];

        if ($event->image_path) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $event->image_path)) {
                $galleryImage = GalleryImage::query()
                    ->where('image_id', $event->image_path)
                    ->where('user_id', $event->user_id)
                    ->where('is_deleted', false)
                    ->first();

                $payload['image_url'] = $galleryImage ? MediaHelper::url($galleryImage->file_path) : null;
            } else {
                $payload['image_url'] = MediaHelper::url($event->image_path);
            }
        } else {
            $payload['image_url'] = null;
        }

        $additionalImages = $event->additional_images ?? [];
        $additionalImageUrls = [];

        if (! empty($additionalImages) && is_array($additionalImages)) {
            $galleryImages = GalleryImage::query()
                ->whereIn('image_id', $additionalImages)
                ->where('user_id', $event->user_id)
                ->where('is_deleted', false)
                ->get()
                ->keyBy('image_id');

            foreach ($additionalImages as $imageId) {
                if (isset($galleryImages[$imageId])) {
                    $additionalImageUrls[] = MediaHelper::url($galleryImages[$imageId]->file_path);
                }
            }
        }

        $payload['additional_images'] = $additionalImages;
        $payload['additional_image_urls'] = $additionalImageUrls;
        $payload['occurrence'] = $this->occurrenceMeta($event);

        return [
            'success' => true,
            'message' => 'Event occurrence fetched successfully',
            'data' => $payload,
        ];
    }

    /**
     * Update one occurrence; marks is_modified=true. Never updates RecurringSeries.
     */
    public function update(EventV2 $event, array $data, User $user, ?Request $request = null): EventV2
    {
        $this->assertSeriesOccurrence($event);
        $this->assertOwner($event, $user);

        unset($data['series_id'], $data['is_modified']);

        return DB::transaction(function () use ($event, $data, $request, $user) {
            $wasModified = (bool) $event->is_modified;

            $updated = $this->eventService->update($event, $data, $request, sendInvitations: true);

            if (! $updated->is_modified) {
                EventV2::query()
                    ->whereKey($updated->id)
                    ->update(['is_modified' => true]);
                $updated->refresh();
            }

            $updated->load(['category', 'venue', 'organisers', 'talents', 'user', 'recurringSeries']);
            $updated->setRelation('subcategories', $updated->subcategories_from_ids);

            Log::info('Recurring event occurrence updated individually', [
                'event_id' => $updated->id,
                'series_id' => $updated->series_id,
                'user_id' => $user->id,
                'newly_modified' => ! $wasModified,
            ]);

            $this->updateNotificationService->dispatchForEventsAfterCommit([(int) $updated->id], $user);

            return $updated;
        });
    }

    /**
     * Cancel one future occurrence (delete or cancel per invitation rules).
     *
     * @return array{deleted: int, cancelled: int, skipped_past: int, skipped_already_handled: int, notified: int}
     */
    public function cancel(EventV2 $event, User $user): array
    {
        $this->assertSeriesOccurrence($event);
        $this->assertOwner($event, $user);

        return $this->lifecycleService->cancelOccurrence($event, $user, 'occurrence_cancel');
    }

    private function assertSeriesOccurrence(EventV2 $event): void
    {
        if (! $event->isSeriesInstance()) {
            throw new NotFoundHttpException('This event is not part of a recurring series.');
        }
    }

    private function assertOwner(EventV2 $event, User $user): void
    {
        if (! $event->isOwner($user) && ! $user->isAdmin()) {
            throw new AuthorizationException('You are not authorized to manage this event occurrence.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function occurrenceMeta(EventV2 $event): array
    {
        $series = $event->recurringSeries;

        return [
            'series_id' => $event->series_id,
            'is_modified' => (bool) $event->is_modified,
            'is_series_instance' => true,
            'recurring_series' => $series ? [
                'id' => $series->id,
                'recurrence_type' => $series->recurrence_type,
                'timezone' => $series->timezone,
                'start_date' => $series->start_date?->format('Y-m-d'),
                'end_date' => $series->end_date?->format('Y-m-d'),
            ] : null,
        ];
    }
}
