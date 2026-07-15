<?php

namespace App\Services\V2;

use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Support\Recurrence\RecurringInstancePayloadBuilder;
use App\Support\Recurrence\RecurringOccurrenceCalculator;
use App\Support\Recurrence\RecurringSeriesTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Propagates series template + schedule changes to eligible future event instances.
 *
 * - Skips is_modified=true instances (never update or delete)
 * - Skips past instances
 * - Removes obsolete future unmodified instances
 * - Regenerates missing future occurrences via RecurringEventGenerationService
 */
class RecurringSeriesPropagationService
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly RecurringEventGenerationService $generationService,
        private readonly EventInstanceDispositionService $dispositionService,
        private readonly EventInstanceUpdateNotificationService $updateNotificationService,
    ) {}

    /**
     * @return array{
     *   updated: int,
     *   removed: int,
     *   skipped_modified: int,
     *   skipped_past: int,
     *   generation: array<string, int>
     * }
     */
    public function propagate(RecurringSeries $series, User $actor): array
    {
        $series->loadMissing('organizer');
        $timezone = $series->timezone;
        $now = Carbon::now($timezone);

        $template = RecurringSeriesTemplate::eventTemplate($series);
        $expectedSlots = $this->expectedFutureSlots($series, $now);

        $stats = [
            'updated' => 0,
            'removed' => 0,
            'skipped_modified' => 0,
            'skipped_past' => 0,
            'generation' => [
                'created' => 0,
                'skipped_existing' => 0,
                'skipped_past' => 0,
                'total_candidates' => 0,
            ],
        ];

        return DB::transaction(function () use ($series, $template, $expectedSlots, $timezone, $now, $actor, &$stats) {
            $instances = EventV2::query()
                ->where('series_id', $series->id)
                ->get();

            $obsoleteInstances = [];
            $updatedEventIds = [];

            foreach ($instances as $instance) {
                if ($instance->is_modified) {
                    $stats['skipped_modified']++;

                    continue;
                }

                if ($instance->start_datetime === null) {
                    continue;
                }

                $instanceStart = Carbon::parse($instance->start_datetime, $timezone);

                if ($instanceStart->lessThan($now)) {
                    $stats['skipped_past']++;

                    continue;
                }

                $instanceKey = $instanceStart->format('Y-m-d H:i:s');

                if (isset($expectedSlots[$instanceKey])) {
                    $updatePayload = RecurringInstancePayloadBuilder::forUpdate(
                        RecurringInstancePayloadBuilder::build(
                            $template,
                            $series,
                            $expectedSlots[$instanceKey]
                        )
                    );

                    $this->eventService->update($instance, $updatePayload, null, sendInvitations: false);
                    $stats['updated']++;
                    $updatedEventIds[] = (int) $instance->id;

                    Log::info('Recurring series propagated to instance', [
                        'series_id' => $series->id,
                        'event_id' => $instance->id,
                        'start_datetime' => $instanceKey,
                    ]);
                } else {
                    $obsoleteInstances[] = $instance;
                }
            }

            if ($obsoleteInstances !== []) {
                $disposed = $this->dispositionService->disposeMany($obsoleteInstances, $actor, 'propagation_remove');
                $stats['removed'] = ($disposed['deleted'] ?? 0) + ($disposed['cancelled'] ?? 0);
            }

            $stats['generation'] = $this->generationService->generate($series->fresh(['organizer']));

            if ($updatedEventIds !== []) {
                $this->updateNotificationService->dispatchForEventsAfterCommit($updatedEventIds, $actor);
            }

            Log::info('Recurring series propagation completed', [
                'series_id' => $series->id,
                'stats' => $stats,
            ]);

            return $stats;
        });
    }

    /**
     * @return array<string, array{start: \Carbon\CarbonInterface, end: \Carbon\CarbonInterface, start_date: string, end_date: string|null}>
     */
    private function expectedFutureSlots(RecurringSeries $series, Carbon $now): array
    {
        if ($series->recurrence_type !== 'weekly') {
            throw new InvalidArgumentException('Propagation supports weekly recurrence only.');
        }

        $timezone = $series->timezone;
        $dates = RecurringOccurrenceCalculator::datesForSeries($series);
        $timeSlots = RecurringSeriesTemplate::timeSlots($series);
        $expected = [];

        foreach ($dates as $date) {
            foreach ($timeSlots as $slot) {
                $datetimes = RecurringOccurrenceCalculator::datetimesForSlot($date, $slot, $timezone);

                if ($datetimes['start']->lessThan($now)) {
                    continue;
                }

                $key = $datetimes['start']->format('Y-m-d H:i:s');
                $expected[$key] = $datetimes;
            }
        }

        return $expected;
    }
}
