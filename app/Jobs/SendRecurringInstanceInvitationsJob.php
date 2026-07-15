<?php

namespace App\Jobs;

use App\Models\EventInvitation;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Services\V2\EventInvitationService;
use App\Services\V2\RecurringSeriesInvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Creates invitation rows (and optionally sends notifications) for a chunk of series instances.
 */
class SendRecurringInstanceInvitationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<int>  $eventIds
     */
    public function __construct(
        public readonly int $seriesId,
        public readonly array $eventIds,
        public readonly bool $notificationsOnly = false,
        /** @var list<int> */
        public readonly array $invitationIds = [],
    ) {}

    /**
     * @param  list<int>  $invitationIds
     */
    public static function dispatchNotifications(array $invitationIds): void
    {
        if ($invitationIds === []) {
            return;
        }

        self::dispatch(0, [], true, $invitationIds);
    }

    public function handle(
        RecurringSeriesInvitationService $seriesInvitationService,
        EventInvitationService $invitationService,
    ): void {
        if ($this->notificationsOnly) {
            $invitations = EventInvitation::query()
                ->whereIn('id', $this->invitationIds)
                ->get()
                ->all();

            $invitationService->sendNotificationsForInvitations($invitations);

            return;
        }

        $series = RecurringSeries::with('organizer')->find($this->seriesId);

        if (! $series) {
            Log::warning('SendRecurringInstanceInvitationsJob: series not found', [
                'series_id' => $this->seriesId,
            ]);

            return;
        }

        $organizer = $series->organizer ?? User::find($series->organizer_id);

        if (! $organizer) {
            return;
        }

        $seriesInvitationService->createForEventIds($this->seriesId, $this->eventIds, $organizer);
    }
}
