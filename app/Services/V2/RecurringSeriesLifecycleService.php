<?php

namespace App\Services\V2;

use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Delete / cancel recurring series instances per invitation business rules.
 */
class RecurringSeriesLifecycleService
{
    public function __construct(
        private readonly EventInstanceDispositionService $dispositionService,
    ) {}

    /**
     * @return array{deleted: int, cancelled: int, skipped_past: int, skipped_already_handled: int, notified: int}
     */
    public function deleteSeries(RecurringSeries $series, User $actor, string $context = 'series_delete'): array
    {
        return DB::transaction(function () use ($series, $actor, $context) {
            $stats = $this->processAllFutureInstances($series, $actor, $context);

            $seriesId = $series->id;
            $organizerId = $series->organizer_id;

            $this->detachPastInstancesBeforeSeriesDelete($series);
            $series->delete();

            $this->auditLog($context, $actor, [
                'series_id' => $seriesId,
                'organizer_id' => $organizerId,
                'lifecycle' => $stats,
            ]);

            return $stats;
        });
    }

    /**
     * @return array{deleted: int, cancelled: int, skipped_past: int, skipped_already_handled: int, notified: int}
     */
    public function cancelSeries(RecurringSeries $series, User $actor, string $context = 'series_cancel'): array
    {
        return DB::transaction(function () use ($series, $actor, $context) {
            $stats = $this->processAllFutureInstances($series, $actor, $context);

            $this->auditLog($context, $actor, [
                'series_id' => $series->id,
                'organizer_id' => $series->organizer_id,
                'lifecycle' => $stats,
            ]);

            return $stats;
        });
    }

    /**
     * @return array{deleted: int, cancelled: int, skipped_past: int, skipped_already_handled: int, notified: int}
     */
    public function processEndDateShortened(
        RecurringSeries $series,
        mixed $previousEndDate,
        mixed $newEndDate,
        User $actor,
    ): array {
        if (! $this->isEndDateShortened($previousEndDate, $newEndDate)) {
            return $this->emptyStats();
        }

        return DB::transaction(function () use ($series, $newEndDate, $actor) {
            $stats = $this->emptyStats();
            $timezone = $series->timezone;
            $now = Carbon::now($timezone);
            $boundary = Carbon::parse($newEndDate, $timezone)->endOfDay();

            $instances = EventV2::query()
                ->where('series_id', $series->id)
                ->whereNotNull('start_datetime')
                ->where('start_datetime', '>', $boundary->utc())
                ->get();

            $futureInstances = $instances->filter(
                fn (EventV2 $instance) => $this->isFutureInstance($instance, $timezone, $now)
            );

            $toDispose = $futureInstances->reject(fn (EventV2 $instance) => $instance->is_modified);

            $stats['skipped_past'] += $instances->count() - $futureInstances->count();
            $stats['skipped_already_handled'] += $futureInstances->count() - $toDispose->count();

            $disposed = $this->dispositionService->disposeMany($toDispose, $actor, 'end_date_shorten');
            $stats = $this->mergeStats($stats, $disposed);

            $this->auditLog('end_date_shorten', $actor, [
                'series_id' => $series->id,
                'new_end_date' => Carbon::parse($newEndDate)->format('Y-m-d'),
                'lifecycle' => $stats,
            ]);

            return $stats;
        });
    }

    /**
     * @return array{deleted: int, cancelled: int, skipped_past: int, skipped_already_handled: int, notified: int, series_processed: int}
     */
    public function handlePremiumExpiry(User $publisher, User $actor): array
    {
        return DB::transaction(function () use ($publisher, $actor) {
            $stats = array_merge($this->emptyStats(), ['series_processed' => 0]);

            $seriesList = RecurringSeries::query()
                ->where('organizer_id', $publisher->id)
                ->get();

            foreach ($seriesList as $series) {
                $seriesStats = $this->deleteSeries($series, $actor, 'premium_expiry');
                $stats = $this->mergeStats($stats, $seriesStats);
                $stats['series_processed']++;
            }

            $this->auditLog('premium_expiry', $actor, [
                'publisher_id' => $publisher->id,
                'lifecycle' => $stats,
            ]);

            return $stats;
        });
    }

    /**
     * @return array{deleted: int, cancelled: int, skipped_past: int, skipped_already_handled: int, notified: int}
     */
    public function cancelOccurrence(EventV2 $event, User $actor, string $context = 'occurrence_cancel'): array
    {
        return DB::transaction(function () use ($event, $actor, $context) {
            $stats = $this->emptyStats();

            if (! $event->isSeriesInstance()) {
                throw new \InvalidArgumentException('Event is not a recurring series occurrence.');
            }

            $series = $event->recurringSeries;
            $timezone = $series?->timezone ?? config('app.timezone');
            $now = Carbon::now($timezone);

            if (! $this->isFutureInstance($event, $timezone, $now)) {
                $stats['skipped_past'] = 1;

                return $stats;
            }

            $disposed = $this->dispositionService->disposeMany(collect([$event]), $actor, $context);
            $stats = $this->mergeStats($stats, $disposed);

            $this->auditLog($context, $actor, [
                'event_id' => $event->id,
                'series_id' => $event->series_id,
                'lifecycle' => $stats,
            ]);

            return $stats;
        });
    }

    public function hasAcceptedInvitations(EventV2 $event): bool
    {
        return $this->dispositionService->hasAcceptedInvitations($event);
    }

    public function isEndDateShortened(mixed $previousEndDate, mixed $newEndDate): bool
    {
        if ($newEndDate === null || $newEndDate === '') {
            return false;
        }

        if ($previousEndDate === null || $previousEndDate === '') {
            return true;
        }

        return Carbon::parse($newEndDate)->lt(Carbon::parse($previousEndDate));
    }

    /**
     * @return array{deleted: int, cancelled: int, skipped_past: int, skipped_already_handled: int, notified: int}
     */
    private function processAllFutureInstances(
        RecurringSeries $series,
        User $actor,
        string $context,
    ): array {
        $stats = $this->emptyStats();
        $now = Carbon::now($series->timezone);

        $instances = $this->futureInstancesQuery($series->id, $now)->get();
        $disposed = $this->dispositionService->disposeMany($instances, $actor, $context);

        return $this->mergeStats($stats, $disposed);
    }

    private function futureInstancesQuery(int $seriesId, Carbon $now): Builder
    {
        return EventV2::query()
            ->where('series_id', $seriesId)
            ->whereNotNull('start_datetime')
            ->where('start_datetime', '>=', $now->utc());
    }

    private function isFutureInstance(EventV2 $event, string $timezone, Carbon $now): bool
    {
        if ($event->start_datetime === null) {
            return false;
        }

        return Carbon::parse($event->start_datetime, $timezone)->gte($now);
    }

    /**
     * Past instances keep their row but must not remain flagged as active recurring
     * occurrences once the series definition is removed (FK nullOnDelete).
     */
    private function detachPastInstancesBeforeSeriesDelete(RecurringSeries $series): void
    {
        $now = Carbon::now($series->timezone)->utc();

        EventV2::query()
            ->where('series_id', $series->id)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('start_datetime')
                    ->orWhere('start_datetime', '<', $now);
            })
            ->update(['is_recurring' => false]);
    }

    /**
     * @return array{deleted: int, cancelled: int, skipped_past: int, skipped_already_handled: int, notified: int}
     */
    private function emptyStats(): array
    {
        return [
            'deleted' => 0,
            'cancelled' => 0,
            'skipped_past' => 0,
            'skipped_already_handled' => 0,
            'notified' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $base
     * @param  array<string, int>  $add
     * @return array<string, int>
     */
    private function mergeStats(array $base, array $add): array
    {
        foreach ($add as $key => $value) {
            $base[$key] = ($base[$key] ?? 0) + $value;
        }

        // Map disposition skipped to lifecycle skipped_already_handled for API compatibility
        if (isset($add['skipped_already_handled'])) {
            $base['skipped_already_handled'] = ($base['skipped_already_handled'] ?? 0) + $add['skipped_already_handled'];
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function auditLog(string $action, User $actor, array $context): void
    {
        Log::info('Recurring series lifecycle', array_merge([
            'action' => $action,
            'actor_id' => $actor->id,
        ], $context));
    }
}
