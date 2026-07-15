<?php

namespace App\Services\Admin;

use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Support\PublishStatus;
use App\Services\V2\EventService;
use App\Services\V2\RecurringSeriesLifecycleService;
use App\Support\Recurrence\RecurrenceRulesSchema;
use App\Support\Recurrence\RecurringSeriesTemplate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin moderation for recurring series and their materialized occurrences.
 */
class AdminRecurringSeriesService
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly RecurringSeriesLifecycleService $lifecycleService,
    ) {}

    public function seriesName(RecurringSeries $series): string
    {
        $rules = is_array($series->recurrence_rules) ? $series->recurrence_rules : [];
        $template = $rules[RecurringSeriesTemplate::KEY_EVENT_TEMPLATE] ?? null;

        if (is_array($template) && ! empty($template['title'])) {
            return (string) $template['title'];
        }

        $sourceEventId = $rules[RecurrenceRulesSchema::KEY_SOURCE_EVENT_ID] ?? null;
        if ($sourceEventId) {
            $title = EventV2::query()->whereKey((int) $sourceEventId)->value('title');
            if (is_string($title) && $title !== '') {
                return $title;
            }
        }

        return "Series #{$series->id}";
    }

    public function recurrencePattern(RecurringSeries $series): string
    {
        $type = ucfirst((string) $series->recurrence_type);
        $rules = is_array($series->recurrence_rules) ? $series->recurrence_rules : [];
        $weekdays = $rules['weekdays'] ?? [];
        $labels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        if (is_array($weekdays) && $weekdays !== []) {
            $days = implode(', ', array_map(
                fn ($d) => $labels[(int) $d] ?? (string) $d,
                array_values($weekdays),
            ));

            return "{$type} · {$days}";
        }

        if (! empty($rules['day_of_month'])) {
            return "{$type} · Day {$rules['day_of_month']}";
        }

        return $type;
    }

    /**
     * @return array{event_id: int, event_date: string, start_time: string|null, start_datetime: string|null, status: string}|null
     */
    public function nextOccurrence(RecurringSeries $series): ?array
    {
        $event = $this->futureInstancesQuery($series)
            ->whereIn('status', [EventV2::STATUS_UPCOMING, EventV2::STATUS_LIVE])
            ->orderBy('start_datetime')
            ->first();

        if (! $event) {
            return null;
        }

        return [
            'event_id' => $event->id,
            'event_date' => $event->event_date?->format('Y-m-d') ?? '',
            'start_time' => $event->start_time,
            'start_datetime' => $event->start_datetime?->toIso8601String(),
            'status' => $event->status,
        ];
    }

    public function computedStatus(RecurringSeries $series): string
    {
        if (! $series->is_approved) {
            return 'pending';
        }

        $future = $this->futureInstancesQuery($series)
            ->whereNotIn('status', [EventV2::STATUS_CANCELLED, EventV2::STATUS_COMPLETED])
            ->get();

        if ($future->isEmpty()) {
            return 'ended';
        }

        $active = $future->reject(fn (EventV2 $event) => $event->status === EventV2::STATUS_SUSPENDED);

        return $active->isEmpty() ? 'suspended' : 'active';
    }

    /**
     * @return array{instances_approved: int, instances_published: int}
     */
    public function approve(RecurringSeries $series, User $admin): array
    {
        return DB::transaction(function () use ($series, $admin) {
            $series->update([
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]);

            $instancesApproved = 0;

            EventV2::query()
                ->where('series_id', $series->id)
                ->where('is_approved', false)
                ->whereNotIn('status', [EventV2::STATUS_CANCELLED, EventV2::STATUS_COMPLETED])
                ->orderBy('id')
                ->chunkById(100, function (Collection $events) use ($admin, &$instancesApproved) {
                    foreach ($events as $event) {
                        $this->eventService->approve($event, $admin);
                        $instancesApproved++;
                    }
                });

            $instancesPublished = EventV2::query()
                ->where('series_id', $series->id)
                ->where('publish_status', PublishStatus::DRAFT)
                ->whereNotIn('status', [EventV2::STATUS_CANCELLED, EventV2::STATUS_COMPLETED])
                ->update(['publish_status' => PublishStatus::PUBLISHED]);

            $this->audit('series_approve', $admin, [
                'series_id' => $series->id,
                'organizer_id' => $series->organizer_id,
                'instances_approved' => $instancesApproved,
                'instances_published' => $instancesPublished,
            ]);

            return [
                'instances_approved' => $instancesApproved,
                'instances_published' => $instancesPublished,
            ];
        });
    }

    /**
     * Reject (unapprove) series and all approved instances.
     *
     * @return array{instances_unapproved: int}
     */
    public function reject(RecurringSeries $series, User $admin): array
    {
        return DB::transaction(function () use ($series, $admin) {
            $series->update([
                'is_approved' => false,
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $instancesUnapproved = EventV2::query()
                ->where('series_id', $series->id)
                ->where('is_approved', true)
                ->update([
                    'is_approved' => false,
                    'approved_at' => null,
                    'approved_by' => null,
                ]);

            $this->audit('series_reject', $admin, [
                'series_id' => $series->id,
                'organizer_id' => $series->organizer_id,
                'instances_unapproved' => $instancesUnapproved,
            ]);

            return ['instances_unapproved' => $instancesUnapproved];
        });
    }

    /**
     * Suspend all future non-cancelled/completed occurrences.
     *
     * @return array{suspended: int, skipped: int}
     */
    public function suspendSeries(RecurringSeries $series, User $admin, ?string $reason = null): array
    {
        return DB::transaction(function () use ($series, $admin, $reason) {
            $suspended = 0;
            $skipped = 0;

            $this->futureInstancesQuery($series)
                ->whereNotIn('status', [
                    EventV2::STATUS_CANCELLED,
                    EventV2::STATUS_COMPLETED,
                    EventV2::STATUS_SUSPENDED,
                ])
                ->orderBy('id')
                ->chunkById(100, function (Collection $events) use ($admin, $reason, &$suspended, &$skipped) {
                    foreach ($events as $event) {
                        $this->eventService->suspend($event, $admin, $reason);
                        $suspended++;
                    }
                });

            $this->audit('series_suspend', $admin, [
                'series_id' => $series->id,
                'organizer_id' => $series->organizer_id,
                'reason' => $reason,
                'suspended' => $suspended,
                'skipped' => $skipped,
            ]);

            return ['suspended' => $suspended, 'skipped' => $skipped];
        });
    }

    /**
     * Unsuspend future suspended occurrences.
     *
     * @return array{unsuspended: int}
     */
    public function unsuspendSeries(RecurringSeries $series, User $admin): array
    {
        return DB::transaction(function () use ($series, $admin) {
            $unsuspended = 0;

            $this->futureInstancesQuery($series)
                ->where('status', EventV2::STATUS_SUSPENDED)
                ->orderBy('id')
                ->chunkById(100, function (Collection $events) use ($admin, &$unsuspended) {
                    foreach ($events as $event) {
                        $this->eventService->unsuspend($event, $admin);
                        $unsuspended++;
                    }
                });

            $this->audit('series_unsuspend', $admin, [
                'series_id' => $series->id,
                'organizer_id' => $series->organizer_id,
                'unsuspended' => $unsuspended,
            ]);

            return ['unsuspended' => $unsuspended];
        });
    }

    /**
     * @return array{total: int, pending: int, accepted: int, rejected: int}
     */
    public function invitationSummary(RecurringSeries $series): array
    {
        $eventIds = EventV2::query()
            ->where('series_id', $series->id)
            ->pluck('id');

        if ($eventIds->isEmpty()) {
            return ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0, 'configured' => 0];
        }

        $counts = EventInvitation::query()
            ->whereIn('event_id', $eventIds)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'pending' => (int) ($counts[EventInvitation::STATUS_PENDING] ?? 0),
            'accepted' => (int) ($counts[EventInvitation::STATUS_ACCEPTED] ?? 0),
            'rejected' => (int) ($counts[EventInvitation::STATUS_REJECTED] ?? 0),
            'configured' => $this->configuredInviteesAcrossSeries($eventIds),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $eventIds
     */
    private function configuredInviteesAcrossSeries($eventIds): int
    {
        return (int) EventV2::query()
            ->whereIn('id', $eventIds)
            ->get(['invited_talents', 'invited_organisers', 'invited_venues'])
            ->sum(function (EventV2 $event): int {
                $talents = is_array($event->invited_talents) ? count($event->invited_talents) : 0;
                $organisers = is_array($event->invited_organisers) ? count($event->invited_organisers) : 0;
                $venues = is_array($event->invited_venues) ? count($event->invited_venues) : 0;

                return $talents + $organisers + $venues;
            });
    }

    /**
     * @return Collection<int, EventV2>
     */
    public function occurrences(RecurringSeries $series, int $limit = 100): Collection
    {
        return EventV2::query()
            ->where('series_id', $series->id)
            ->withCount([
                'invitations as invitations_total_count',
                'invitations as invitations_accepted_count' => fn (Builder $q) => $q->where(
                    'status',
                    EventInvitation::STATUS_ACCEPTED,
                ),
                'invitations as invitations_pending_count' => fn (Builder $q) => $q->where(
                    'status',
                    EventInvitation::STATUS_PENDING,
                ),
            ])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    public function applyStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'pending' => $query->where('is_approved', false),
            'active' => $query->where('is_approved', true)
                ->whereHas('events', fn (Builder $q) => $this->applyFutureActiveScope($q)),
            'suspended' => $query->where('is_approved', true)
                ->whereHas('events', fn (Builder $q) => $this->applyFutureSuspendedScope($q))
                ->whereDoesntHave('events', fn (Builder $q) => $this->applyFutureActiveScope($q)),
            'ended' => $query->where('is_approved', true)
                ->whereDoesntHave('events', fn (Builder $q) => $this->applyFutureActiveScope($q)),
            default => $query,
        };
    }

    private function futureInstancesQuery(RecurringSeries $series): Builder
    {
        $now = Carbon::now($series->timezone)->utc();

        return EventV2::query()
            ->where('series_id', $series->id)
            ->whereNotNull('start_datetime')
            ->where('start_datetime', '>=', $now);
    }

    private function applyFutureActiveScope(Builder $query): Builder
    {
        return $query
            ->whereNotNull('start_datetime')
            ->where('start_datetime', '>=', now())
            ->whereNotIn('status', [
                EventV2::STATUS_CANCELLED,
                EventV2::STATUS_COMPLETED,
                EventV2::STATUS_SUSPENDED,
            ]);
    }

    private function applyFutureSuspendedScope(Builder $query): Builder
    {
        return $query
            ->whereNotNull('start_datetime')
            ->where('start_datetime', '>=', now())
            ->where('status', EventV2::STATUS_SUSPENDED);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function audit(string $action, User $actor, array $context): void
    {
        Log::info('Admin recurring series moderation', array_merge([
            'action' => $action,
            'actor_id' => $actor->id,
        ], $context));
    }
}
