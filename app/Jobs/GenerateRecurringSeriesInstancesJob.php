<?php

namespace App\Jobs;

use App\Models\RecurringSeries;
use App\Services\V2\RecurringEventGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued materialization of recurring series event instances.
 *
 * Dispatched when config('recurring.queue_generation') is true.
 */
class GenerateRecurringSeriesInstancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $seriesId,
    ) {}

    public function handle(RecurringEventGenerationService $generationService): void
    {
        $series = RecurringSeries::with('organizer')->find($this->seriesId);

        if (! $series) {
            Log::warning('GenerateRecurringSeriesInstancesJob: series not found', [
                'series_id' => $this->seriesId,
            ]);

            return;
        }

        $generationService->generate($series);
    }
}
