<?php

namespace App\Services\V2;

use App\Jobs\NotifyRecurringInstanceParticipantsJob;
use App\Mail\EventUpdateMail;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifies accepted talent/venue/organiser invitees when an event occurrence is updated.
 */
class EventInstanceUpdateNotificationService
{
    public function __construct(
        private readonly FirebaseNotificationService $firebaseNotificationService,
    ) {}

    /**
     * @param  list<int>  $eventIds
     */
    public function dispatchForEvents(array $eventIds, User $actor): void
    {
        $eventIds = array_values(array_unique(array_map('intval', $eventIds)));

        if ($eventIds === []) {
            return;
        }

        if (config('recurring.queue_update_notifications', true)) {
            $chunkSize = max(1, (int) config('recurring.update_notification_chunk_size', 25));

            foreach (array_chunk($eventIds, $chunkSize) as $chunk) {
                NotifyRecurringInstanceParticipantsJob::dispatch($chunk, $actor->id);
            }

            Log::info('Recurring instance update notifications queued', [
                'event_ids' => $eventIds,
                'actor_id' => $actor->id,
            ]);

            return;
        }

        foreach ($eventIds as $eventId) {
            $event = EventV2::find($eventId);

            if ($event) {
                $this->notifyAcceptedParticipants($event, $actor);
            }
        }
    }

    /**
     * @return int Number of invitees notified
     */
    public function notifyAcceptedParticipants(EventV2 $event, User $actor): int
    {
        $invitations = EventInvitation::query()
            ->where('event_id', $event->id)
            ->where('status', EventInvitation::STATUS_ACCEPTED)
            ->whereIn('receiver_type', [
                EventInvitation::TYPE_TALENT,
                EventInvitation::TYPE_ORGANISER,
                EventInvitation::TYPE_VENUE,
            ])
            ->with(['receiver', 'event'])
            ->get();

        if ($invitations->isEmpty()) {
            return 0;
        }

        $event->loadMissing('user');
        $notified = 0;

        foreach ($invitations as $invitation) {
            $this->firebaseNotificationService->createEventUpdateNotification($event, $invitation, $actor);

            $receiver = $invitation->receiver;
            if ($receiver?->email) {
                Mail::to($receiver->email)->send(new EventUpdateMail($event, $invitation, $actor));
            }

            $notified++;
        }

        Log::info('Event occurrence update notifications sent', [
            'event_id' => $event->id,
            'series_id' => $event->series_id,
            'notified' => $notified,
            'actor_id' => $actor->id,
        ]);

        return $notified;
    }

    /**
     * Notify after the surrounding transaction commits (propagation / occurrence edit).
     *
     * @param  list<int>  $eventIds
     */
    public function dispatchForEventsAfterCommit(array $eventIds, User $actor): void
    {
        $eventIds = array_values(array_unique(array_map('intval', $eventIds)));

        if ($eventIds === []) {
            return;
        }

        $actorId = (int) $actor->id;

        DB::afterCommit(function () use ($eventIds, $actorId): void {
            $actor = User::find($actorId);

            if (! $actor) {
                return;
            }

            $this->dispatchForEvents($eventIds, $actor);
        });
    }
}
