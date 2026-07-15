<?php

namespace App\Services\V2;

use App\Jobs\GenerateRecurringSeriesInstancesJob;
use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Support\Recurrence\RecurrenceRulesSchema;
use App\Support\Recurrence\RecurringEventTemplateFromEvent;
use App\Support\Recurrence\RecurringSeriesTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringSeriesService
{
    public function __construct(
        private readonly RecurringEventGenerationService $generationService,
        private readonly RecurringSeriesPropagationService $propagationService,
        private readonly RecurringSeriesLifecycleService $lifecycleService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{series: RecurringSeries, generation?: array<string, int>|null, propagation?: array<string, mixed>|null}
     */
    public function create(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user) {
            $recurrenceRules = $this->prepareRecurrenceRules($data);

            $series = RecurringSeries::create([
                'organizer_id' => $user->id,
                'recurrence_type' => $data['recurrence_type'],
                'recurrence_rules' => $recurrenceRules,
                'timezone' => $data['timezone'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'is_approved' => false,
            ]);

            Log::info('Recurring series created', [
                'series_id' => $series->id,
                'organizer_id' => $user->id,
            ]);

            $series = $series->fresh(['organizer']);
            $generation = $this->dispatchGeneration($series);

            return [
                'series' => $series,
                'generation' => $generation,
                'propagation' => null,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{series: RecurringSeries, generation?: array<string, int>|null, propagation?: array<string, mixed>|null, lifecycle?: array<string, int>|null}
     */
    public function update(RecurringSeries $series, array $data, User $user): array
    {
        $applyToFuture = (bool) ($data['apply_to_future'] ?? false);
        unset($data['apply_to_future']);

        return DB::transaction(function () use ($series, $data, $user, $applyToFuture) {
            $previousEndDate = $series->end_date?->format('Y-m-d');

            $payload = ['updated_by' => $user->id];

            foreach (['recurrence_type', 'timezone', 'start_date', 'end_date'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }

            if (array_key_exists('recurrence_rules', $data) || array_key_exists('event_template', $data)) {
                $currentRules = is_array($series->recurrence_rules) ? $series->recurrence_rules : [];
                $mergedRules = array_key_exists('recurrence_rules', $data)
                    ? array_replace_recursive($currentRules, $data['recurrence_rules'])
                    : $currentRules;

                $prepare = ['recurrence_rules' => $mergedRules];
                if (array_key_exists('event_template', $data)) {
                    $prepare['event_template'] = $data['event_template'];
                }

                $payload['recurrence_rules'] = $this->prepareRecurrenceRules($prepare);
            }

            $series->update($payload);

            Log::info('Recurring series updated', [
                'series_id' => $series->id,
                'organizer_id' => $series->organizer_id,
                'apply_to_future' => $applyToFuture,
            ]);

            $series = $series->fresh(['organizer']);

            $propagation = null;
            $generation = null;
            $lifecycle = null;

            $newEndDate = $series->end_date?->format('Y-m-d');

            if ($this->lifecycleService->isEndDateShortened($previousEndDate, $newEndDate)) {
                $lifecycle = $this->lifecycleService->processEndDateShortened(
                    $series,
                    $previousEndDate,
                    $newEndDate,
                    $user,
                );
            }

            if ($applyToFuture) {
                $propagation = $this->propagationService->propagate($series, $user);
                $generation = $propagation['generation'] ?? null;
            }

            return [
                'series' => $series,
                'generation' => $generation,
                'propagation' => $propagation,
                'lifecycle' => $lifecycle,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function regenerate(RecurringSeries $series): array
    {
        return $this->dispatchGeneration($series->fresh(['organizer']));
    }

    /**
     * Delete series and all eligible future instances (delete or cancel per invitation rules).
     *
     * @return array{deleted: int, cancelled: int, skipped_past: int, skipped_already_handled: int, notified: int}
     */
    public function delete(RecurringSeries $series, User $user): array
    {
        return $this->lifecycleService->deleteSeries($series, $user, 'series_delete');
    }

    /**
     * Cancel all future instances; keep the series record.
     *
     * @return array{deleted: int, cancelled: int, skipped_past: int, skipped_already_handled: int, notified: int}
     */
    public function cancel(RecurringSeries $series, User $user): array
    {
        return $this->lifecycleService->cancelSeries($series, $user, 'series_cancel');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareRecurrenceRules(array $data): array
    {
        $rules = $data['recurrence_rules'] ?? [];
        $template = null;

        if (! empty($data['source_event_id'])) {
            $event = EventV2::query()
                ->with(['organisers', 'talents'])
                ->findOrFail((int) $data['source_event_id']);

            $template = RecurringEventTemplateFromEvent::extract($event);
            $rules[RecurrenceRulesSchema::KEY_SOURCE_EVENT_ID] = (int) $event->id;
        }

        if (isset($data['event_template']) && is_array($data['event_template'])) {
            $template = is_array($template)
                ? array_replace_recursive($template, $data['event_template'])
                : $data['event_template'];
        }

        if (is_array($template)) {
            $rules = RecurringSeriesTemplate::mergeTemplateIntoRules($rules, $template);
        }

        return $rules;
    }

    /**
     * @return array<string, int>|null Null when queued (stats available after job runs).
     */
    private function dispatchGeneration(RecurringSeries $series): ?array
    {
        if (config('recurring.queue_generation')) {
            GenerateRecurringSeriesInstancesJob::dispatch($series->id);

            Log::info('Recurring series generation queued', ['series_id' => $series->id]);

            return null;
        }

        return $this->generationService->generate($series);
    }
}
