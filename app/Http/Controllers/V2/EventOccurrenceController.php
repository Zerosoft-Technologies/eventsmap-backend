<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\UpdateEventOccurrenceRequest;
use App\Http\Resources\V2\EventResource;
use App\Models\EventV2;
use App\Services\V2\IndividualInstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * View / update a single recurring series occurrence (never mutates RecurringSeries).
 */
class EventOccurrenceController extends Controller
{
    public function __construct(
        private readonly IndividualInstanceService $individualInstanceService,
    ) {}

    /**
     * GET /api/v2/events/{id}/occurrence
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);

        return response()->json(
            $this->individualInstanceService->show($event, $request->user())
        );
    }

    /**
     * PUT|POST /api/v2/events/{id}/occurrence
     */
    public function update(UpdateEventOccurrenceRequest $request, int $id): JsonResponse
    {
        $event = EventV2::findOrFail($id);

        $data = $request->validated();

        if (
            ! $request->hasFile('additional_images')
            && array_key_exists('additional_images', $request->all())
            && ! array_key_exists('additional_images', $data)
        ) {
            $raw = $request->input('additional_images');
            $data['additional_images'] = is_array($raw) ? $raw : [];
        }

        $updated = $this->individualInstanceService->update(
            $event,
            $data,
            $request->user(),
            $request
        );

        $payload = (new EventResource($updated))->resolve();
        $payload['occurrence'] = [
            'series_id' => $updated->series_id,
            'is_modified' => (bool) $updated->is_modified,
            'is_series_instance' => true,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Event occurrence updated successfully',
            'data' => $payload,
        ]);
    }

    /**
     * POST /api/v2/events/{id}/occurrence/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate(['confirm' => 'required|accepted']);

        $event = EventV2::findOrFail($id);

        $lifecycle = $this->individualInstanceService->cancel($event, $request->user());

        $cancelled = ($lifecycle['cancelled'] ?? 0) > 0;
        $deleted = ($lifecycle['deleted'] ?? 0) > 0;
        $skippedPast = ($lifecycle['skipped_past'] ?? 0) > 0;

        if ($skippedPast && ! $cancelled && ! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Only future occurrences can be cancelled.',
                'lifecycle' => $lifecycle,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $cancelled
                ? 'Event occurrence cancelled successfully'
                : 'Event occurrence removed successfully',
            'lifecycle' => $lifecycle,
        ]);
    }
}
