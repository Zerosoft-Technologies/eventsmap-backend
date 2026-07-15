<?php

namespace App\Services\V2;

use App\Jobs\CreateRecurringSeriesInvitationsJob;
use App\Jobs\SendRecurringInstanceInvitationsJob;
use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Support\Recurrence\RecurringSeriesTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Fans out per-instance invitations for recurring series using existing EventInvitationService.
 */
class RecurringSeriesInvitationService
{
    public function __construct(
        private readonly EventInvitationService $invitationService,
    ) {}

    public function templateHasInvitees(RecurringSeries $series): bool
    {
        try {
            $template = RecurringSeriesTemplate::eventTemplate($series);
        } catch (\Throwable) {
            return false;
        }

        return ! empty($template['invited_talents'] ?? [])
            || ! empty($template['invited_organisers'] ?? [])
            || ! empty($template['invited_venues'] ?? []);
    }

    /**
     * @param  list<int>|null  $eventIds  Limit to specific instances; null = all series instances.
     */
    public function dispatchForSeries(int $seriesId, ?array $eventIds = null): void
    {
        $series = RecurringSeries::query()->find($seriesId);

        if (! $series || ! $this->templateHasInvitees($series)) {
            return;
        }

        if (config('recurring.queue_invitations', true)) {
            CreateRecurringSeriesInvitationsJob::dispatch($seriesId, $eventIds);

            Log::info('Recurring series invitations queued', [
                'series_id' => $seriesId,
                'event_ids' => $eventIds,
            ]);

            return;
        }

        $this->createForSeries($series->fresh(['organizer']), $eventIds);
    }

    /**
     * @param  list<int>|null  $eventIds
     * @return array{events_processed: int, invitations_created: int, invitations_skipped: int}
     */
    public function createForSeries(RecurringSeries $series, ?array $eventIds = null): array
    {
        $stats = [
            'events_processed' => 0,
            'invitations_created' => 0,
            'invitations_skipped' => 0,
        ];

        if (! $this->templateHasInvitees($series)) {
            return $stats;
        }

        $organizer = $series->organizer ?? User::find($series->organizer_id);

        if (! $organizer) {
            Log::warning('Recurring series invitations skipped: organizer not found', [
                'series_id' => $series->id,
            ]);

            return $stats;
        }

        $ids = $eventIds ?? $this->seriesInstanceIds($series->id);

        if ($ids === []) {
            return $stats;
        }

        $chunkSize = max(1, (int) config('recurring.invitation_chunk_size', 25));

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            if (config('recurring.queue_invitations', true)) {
                SendRecurringInstanceInvitationsJob::dispatch($series->id, $chunk);

                continue;
            }

            $chunkStats = $this->createForEventIds($series->id, $chunk, $organizer);
            $stats['events_processed'] += $chunkStats['events_processed'];
            $stats['invitations_created'] += $chunkStats['invitations_created'];
            $stats['invitations_skipped'] += $chunkStats['invitations_skipped'];
        }

        return $stats;
    }

    /**
     * @param  list<int>  $eventIds
     * @return array{events_processed: int, invitations_created: int, invitations_skipped: int}
     */
    public function createForEventIds(int $seriesId, array $eventIds, ?User $organizer = null): array
    {
        $stats = [
            'events_processed' => 0,
            'invitations_created' => 0,
            'invitations_skipped' => 0,
        ];

        if ($eventIds === []) {
            return $stats;
        }

        $series = RecurringSeries::with('organizer')->find($seriesId);

        if (! $series) {
            return $stats;
        }

        $sender = $organizer ?? $series->organizer ?? User::find($series->organizer_id);

        if (! $sender) {
            return $stats;
        }

        $events = EventV2::query()
            ->where('series_id', $seriesId)
            ->whereIn('id', $eventIds)
            ->get();

        $queueNotifications = (bool) config('recurring.queue_invitations', true);
        $pendingNotificationIds = [];

        foreach ($events as $event) {
            if (! $this->eventHasInvitees($event)) {
                continue;
            }

            $result = $this->invitationService->createInvitationsForEvent(
                $event,
                $sender,
                sendNotifications: ! $queueNotifications,
            );

            $stats['events_processed']++;
            $stats['invitations_created'] += count($result['created'] ?? []);
            $stats['invitations_skipped'] += count($result['skipped'] ?? []);

            foreach ($result['created'] ?? [] as $invitation) {
                $pendingNotificationIds[] = $invitation->id;
            }
        }

        if ($queueNotifications && $pendingNotificationIds !== []) {
            SendRecurringInstanceInvitationsJob::dispatchNotifications($pendingNotificationIds);
        }

        Log::info('Recurring series invitations created for instances', [
            'series_id' => $seriesId,
            'stats' => $stats,
        ]);

        return $stats;
    }

    private function eventHasInvitees(EventV2 $event): bool
    {
        return ! empty($event->invited_talents ?? [])
            || ! empty($event->invited_organisers ?? [])
            || ! empty($event->invited_venues ?? []);
    }

    /**
     * @return list<int>
     */
    private function seriesInstanceIds(int $seriesId): array
    {
        return EventV2::query()
            ->where('series_id', $seriesId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
