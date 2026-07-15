<?php

namespace App\Jobs;

use App\Models\RecurringSeries;
use App\Services\V2\RecurringSeriesInvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued fan-out of per-instance invitations after series generation.
 */
class CreateRecurringSeriesInvitationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<int>|null  $eventIds
     */
    public function __construct(
        public readonly int $seriesId,
        public readonly ?array $eventIds = null,
    ) {}

    public function handle(RecurringSeriesInvitationService $invitationService): void
    {
        $series = RecurringSeries::with('organizer')->find($this->seriesId);

        if (! $series) {
            Log::warning('CreateRecurringSeriesInvitationsJob: series not found', [
                'series_id' => $this->seriesId,
            ]);

            return;
        }

        $invitationService->createForSeries($series, $this->eventIds);
    }
}
