<?php

namespace App\Jobs;

use App\Models\EventV2;
use App\Models\User;
use App\Services\V2\EventInstanceUpdateNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued update notifications for accepted invitees on recurring event instances.
 */
class NotifyRecurringInstanceParticipantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<int>  $eventIds
     */
    public function __construct(
        public readonly array $eventIds,
        public readonly int $actorId,
    ) {}

    public function handle(EventInstanceUpdateNotificationService $notificationService): void
    {
        $actor = User::find($this->actorId);

        if (! $actor) {
            Log::warning('NotifyRecurringInstanceParticipantsJob: actor not found', [
                'actor_id' => $this->actorId,
            ]);

            return;
        }

        foreach ($this->eventIds as $eventId) {
            $event = EventV2::find((int) $eventId);

            if ($event) {
                $notificationService->notifyAcceptedParticipants($event, $actor);
            }
        }
    }
}
