<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\DeleteRecurringSeriesRequest;
use App\Http\Requests\V2\StoreRecurringSeriesRequest;
use App\Http\Requests\V2\UpdateRecurringSeriesRequest;
use App\Http\Resources\V2\RecurringSeriesResource;
use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Services\V2\RecurringSeriesService;
use App\Support\Premium\PremiumAccess;
use App\Support\Recurrence\RecurringEventTemplateFromEvent;
use App\Support\Recurrence\RecurringSeriesTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * RecurringSeriesController — V2 CRUD for recurring event series + instance generation.
 */
class RecurringSeriesController extends Controller
{
    public function __construct(
        private readonly RecurringSeriesService $recurringSeriesService,
    ) {}

    /**
     * GET /api/v2/recurring-series
     *
     * List recurring series owned by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'recurrence_type' => 'nullable|string|max:32',
            'sort' => 'nullable|string|in:created_at,start_date',
            'order' => 'nullable|string|in:asc,desc',
        ]);

        $user = $request->user();
        $query = RecurringSeries::query()
            ->with(['organizer'])
            ->withCount('events')
            ->forOrganizer($user->id);

        $query->when($request->filled('recurrence_type'), function ($q) use ($request) {
            $q->type($request->input('recurrence_type'));
        });

        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        $perPage = (int) $request->input('per_page', 20);
        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Recurring series fetched successfully',
            'data' => [
                'series' => RecurringSeriesResource::collection($items->items()),
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/v2/recurring-series/event-templates/{eventId}
     *
     * Build a recurrence event_template from an existing standalone event.
     */
    public function eventTemplate(Request $request, int $eventId): JsonResponse
    {
        $event = EventV2::query()
            ->with(['organisers', 'talents', 'category'])
            ->findOrFail($eventId);

        $user = $request->user();
        if (! $user || ((int) $event->user_id !== (int) $user->id && ! $user->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($event->isSeriesInstance()) {
            return response()->json([
                'success' => false,
                'message' => 'Series instances cannot be used as templates. Select a standalone event.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Event template built successfully',
            'data' => [
                'source_event' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'event_date' => $event->event_date?->format('Y-m-d'),
                    'start_time' => $event->start_time,
                    'end_time' => $event->end_time,
                    'category' => $event->category ? [
                        'id' => $event->category->id,
                        'name' => $event->category->name,
                    ] : null,
                ],
                'event_template' => RecurringEventTemplateFromEvent::extract($event),
            ],
        ]);
    }

    /**
     * POST /api/v2/recurring-series
     */
    public function store(StoreRecurringSeriesRequest $request): JsonResponse
    {
        $result = $this->recurringSeriesService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Recurring series created successfully',
            'data' => new RecurringSeriesResource($result['series']),
            'generation' => $result['generation'],
            'generation_queued' => $result['generation'] === null && config('recurring.queue_generation'),
        ], 201);
    }

    /**
     * GET /api/v2/recurring-series/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $series = RecurringSeries::with(['organizer'])
            ->withCount('events')
            ->findOrFail($id);

        $this->authorize('view', $series);

        return response()->json([
            'success' => true,
            'message' => 'Recurring series fetched successfully',
            'data' => new RecurringSeriesResource($series),
        ]);
    }

    /**
     * PUT /api/v2/recurring-series/{id}
     */
    public function update(UpdateRecurringSeriesRequest $request, int $id): JsonResponse
    {
        $series = RecurringSeries::findOrFail($id);

        if ($request->boolean('apply_to_future')) {
            if ($denied = PremiumAccess::denyResponseUnlessEntitled(
                $request->user(),
                'Premium subscription required to apply changes to future events.'
            )) {
                return $denied;
            }
        }

        $result = $this->recurringSeriesService->update($series, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Recurring series updated successfully',
            'data' => new RecurringSeriesResource($result['series']),
            'generation' => $result['generation'],
            'propagation' => $result['propagation'],
            'lifecycle' => $result['lifecycle'],
        ]);
    }

    /**
     * POST /api/v2/recurring-series/{id}/cancel
     *
     * Cancel all future instances (delete or cancel per invitation rules). Series record is kept.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate(['confirm' => 'required|accepted']);

        $series = RecurringSeries::findOrFail($id);

        $this->authorize('update', $series);

        $lifecycle = $this->recurringSeriesService->cancel($series, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Recurring series future occurrences processed successfully',
            'lifecycle' => $lifecycle,
        ]);
    }

    /**
     * POST /api/v2/recurring-series/{id}/regenerate
     *
     * Idempotently materialize missing future instances for the series.
     */
    public function regenerate(Request $request, int $id): JsonResponse
    {
        $series = RecurringSeries::with(['organizer'])->findOrFail($id);

        $this->authorize('update', $series);

        if ($denied = PremiumAccess::denyResponseUnlessEntitled(
            $request->user(),
            'Premium subscription required to regenerate recurring event instances.'
        )) {
            return $denied;
        }

        try {
            $stats = $this->recurringSeriesService->regenerate($series);
        } catch (\Throwable $e) {
            Log::error('Recurring series regeneration failed', [
                'series_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Recurring series instances regenerated successfully',
            'data' => new RecurringSeriesResource($series->fresh()->loadCount('events')),
            'generation' => $stats,
        ]);
    }

    /**
     * DELETE /api/v2/recurring-series/{id}
     */
    public function destroy(DeleteRecurringSeriesRequest $request, int $id): JsonResponse
    {
        $series = RecurringSeries::findOrFail($id);

        $this->authorize('delete', $series);

        try {
            $lifecycle = $this->recurringSeriesService->delete($series, $request->user());
        } catch (\Throwable $e) {
            Log::error('Failed to delete recurring series', [
                'series_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Recurring series deleted successfully',
            'lifecycle' => $lifecycle,
        ]);
    }
}
