<?php

namespace App\Services\V2;

use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Support\Recurrence\RecurrenceRulesSchema;
use App\Support\Recurrence\RecurrenceType;
use App\Support\Recurrence\RecurringInstancePayloadBuilder;
use App\Support\Recurrence\RecurringOccurrenceCalculator;
use App\Support\Recurrence\RecurringSeriesTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Materializes EventV2 rows for a recurring series using EventService::create().
 *
 * Idempotent: skips existing instances (same series_id + start_datetime).
 * Future-only: skips occurrences whose start is in the past (series timezone).
 */
class RecurringEventGenerationService
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly RecurringSeriesInvitationService $invitationService,
    ) {}

    /**
     * @return array{created: int, skipped_existing: int, skipped_past: int, total_candidates: int, created_event_ids: list<int>}
     */
    public function generate(RecurringSeries $series): array
    {
        $this->assertSeriesReady($series);

        $organizer = $series->organizer ?? User::findOrFail($series->organizer_id);
        $template = RecurringSeriesTemplate::eventTemplate($series);
        $timeSlots = RecurringSeriesTemplate::timeSlots($series);
        $occurrenceDates = RecurringOccurrenceCalculator::datesForSeries($series);
        $timezone = $series->timezone;
        $now = Carbon::now($timezone);

        $stats = [
            'created' => 0,
            'skipped_existing' => 0,
            'skipped_past' => 0,
            'total_candidates' => 0,
            'created_event_ids' => [],
        ];

        $maxInstances = (int) config('recurring.max_instances_per_run', 500);
        $candidateCount = count($occurrenceDates) * count($timeSlots);

        if ($candidateCount > $maxInstances) {
            throw new InvalidArgumentException(
                "Generation would create {$candidateCount} instances; max allowed is {$maxInstances}. Narrow the date range or increase RECURRING_MAX_INSTANCES_PER_RUN."
            );
        }

        $existingStartDatetimes = $this->existingInstanceStartDatetimes($series->id);

        return DB::transaction(function () use (
            $series,
            $organizer,
            $template,
            $timeSlots,
            $occurrenceDates,
            $timezone,
            $now,
            $existingStartDatetimes,
            &$stats,
        ) {
            foreach ($occurrenceDates as $occurrenceDate) {
                foreach ($timeSlots as $slot) {
                    $stats['total_candidates']++;

                    $datetimes = RecurringOccurrenceCalculator::datetimesForSlot(
                        $occurrenceDate,
                        $slot,
                        $timezone
                    );

                    $start = $datetimes['start'];
                    $end = $datetimes['end'];
                    $startKey = $start->format('Y-m-d H:i:s');

                    if ($start->lessThan($now)) {
                        $stats['skipped_past']++;

                        continue;
                    }

                    if (isset($existingStartDatetimes[$startKey])) {
                        $stats['skipped_existing']++;

                        continue;
                    }

                    $instanceData = RecurringInstancePayloadBuilder::build(
                        $template,
                        $series,
                        $datetimes
                    );

                    $event = $this->eventService->create($instanceData, $organizer, sendInvitations: false);

                    $existingStartDatetimes[$startKey] = true;
                    $stats['created']++;
                    $stats['created_event_ids'][] = (int) $event->id;

                    Log::info('Recurring event instance generated', [
                        'series_id' => $series->id,
                        'event_id' => $event->id,
                        'start_datetime' => $startKey,
                    ]);
                }
            }

            Log::info('Recurring series generation completed', [
                'series_id' => $series->id,
                'stats' => $stats,
            ]);

            if ($stats['created_event_ids'] !== [] && $this->invitationService->templateHasInvitees($series)) {
                $this->invitationService->dispatchForSeries($series->id, $stats['created_event_ids']);
            }

            return $stats;
        });
    }

    private function assertSeriesReady(RecurringSeries $series): void
    {
        if ($series->start_date === null) {
            throw new InvalidArgumentException('Series start_date is required for generation.');
        }

        if ($series->end_date !== null && $series->end_date->lessThan($series->start_date)) {
            throw new InvalidArgumentException('Series end_date must be on or after start_date.');
        }

        if ($series->recurrence_type !== RecurrenceType::WEEKLY) {
            throw new InvalidArgumentException(
                "Instance generation supports weekly recurrence only; got [{$series->recurrence_type}]."
            );
        }

        $rules = is_array($series->recurrence_rules) ? $series->recurrence_rules : [];
        $errors = RecurrenceRulesSchema::validateForType($series->recurrence_type, $rules);

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        RecurringSeriesTemplate::eventTemplate($series);
        RecurringSeriesTemplate::timeSlots($series);
    }

    /**
     * @return array<string, true>
     */
    private function existingInstanceStartDatetimes(int $seriesId): array
    {
        return EventV2::query()
            ->where('series_id', $seriesId)
            ->pluck('start_datetime')
            ->filter()
            ->mapWithKeys(static fn ($dt) => [
                Carbon::parse($dt)->format('Y-m-d H:i:s') => true,
            ])
            ->all();
    }
}
