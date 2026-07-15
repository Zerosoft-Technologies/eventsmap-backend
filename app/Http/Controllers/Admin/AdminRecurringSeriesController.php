<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminRecurringSeriesResource;
use App\Models\RecurringSeries;
use App\Services\Admin\AdminRecurringSeriesService;
use App\Services\V2\RecurringSeriesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AdminRecurringSeriesController — admin view, moderation, and lifecycle for recurring series.
 */
class AdminRecurringSeriesController extends Controller
{
    public function __construct(
        private readonly RecurringSeriesService $recurringSeriesService,
        private readonly AdminRecurringSeriesService $adminRecurringSeriesService,
    ) {}

    /**
     * GET /api/admin/recurring-series
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'is_approved' => 'nullable|boolean',
            'status' => 'nullable|string|in:pending,active,suspended,ended',
            'organizer_id' => 'nullable|integer|exists:users,id',
            'recurrence_type' => 'nullable|string|max:32',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort' => 'nullable|string|in:created_at,start_date',
            'order' => 'nullable|string|in:asc,desc',
        ]);

        $query = RecurringSeries::query()
            ->with(['organizer', 'creator'])
            ->withCount('events');

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->input('search');
            $driver = $q->getConnection()->getDriverName();
            $op = $driver === 'pgsql' ? 'ILIKE' : 'like';
            $q->where(function ($sub) use ($search, $op, $driver) {
                $sub->whereHas('organizer', function ($uq) use ($search, $op) {
                    $uq->where('email', $op, "%{$search}%")
                        ->orWhere('name', $op, "%{$search}%");
                });

                if ($driver === 'pgsql') {
                    $sub->orWhereRaw(
                        "recurrence_rules->'event_template'->>'title' {$op} ?",
                        ["%{$search}%"],
                    );
                } else {
                    $sub->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(recurrence_rules, '$.event_template.title')) {$op} ?",
                        ["%{$search}%"],
                    );
                }
            });
        });

        $query->when($request->has('is_approved'), function ($q) use ($request) {
            $q->where('is_approved', $request->boolean('is_approved'));
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $this->adminRecurringSeriesService->applyStatusFilter($q, $request->input('status'));
        });

        $query->when($request->filled('organizer_id'), function ($q) use ($request) {
            $q->where('organizer_id', $request->input('organizer_id'));
        });

        $query->when($request->filled('recurrence_type'), function ($q) use ($request) {
            $q->type($request->input('recurrence_type'));
        });

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->input('date_from', $request->input('date_to'));
            $to = $request->input('date_to', $from);
            $query->overlapping($from, $to);
        }

        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sort, $order);

        $perPage = $request->input('per_page', 20);
        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Recurring series fetched successfully',
            'data' => [
                'series' => AdminRecurringSeriesResource::collection($items->items()),
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
     * GET /api/admin/recurring-series/stats
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total' => RecurringSeries::count(),
            'pending_approval' => RecurringSeries::where('is_approved', false)->count(),
            'approved' => RecurringSeries::where('is_approved', true)->count(),
            'active' => RecurringSeries::query()
                ->tap(fn ($q) => $this->adminRecurringSeriesService->applyStatusFilter($q, 'active'))
                ->count(),
            'suspended' => RecurringSeries::query()
                ->tap(fn ($q) => $this->adminRecurringSeriesService->applyStatusFilter($q, 'suspended'))
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Recurring series stats fetched successfully',
            'data' => $stats,
        ]);
    }

    /**
     * GET /api/admin/recurring-series/{id}
     */
    public function show(int $id): JsonResponse
    {
        $series = RecurringSeries::with(['organizer', 'creator', 'updater', 'approver'])
            ->withCount('events')
            ->findOrFail($id);

        $series->setRelation('events', $this->adminRecurringSeriesService->occurrences($series));
        $series->invitation_summary = $this->adminRecurringSeriesService->invitationSummary($series);

        return response()->json([
            'success' => true,
            'message' => 'Recurring series fetched successfully',
            'data' => new AdminRecurringSeriesResource($series),
        ]);
    }

    /**
     * DELETE /api/admin/recurring-series/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->validate(['confirm' => 'required|accepted']);

        $series = RecurringSeries::findOrFail($id);
        $lifecycle = $this->recurringSeriesService->delete($series, $request->user());

        Log::info('Admin deleted recurring series', [
            'series_id' => $id,
            'actor_id' => $request->user()->id,
            'lifecycle' => $lifecycle,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recurring series deleted successfully',
            'lifecycle' => $lifecycle,
        ]);
    }

    /**
     * POST /api/admin/recurring-series/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate(['confirm' => 'required|accepted']);

        $series = RecurringSeries::findOrFail($id);
        $lifecycle = $this->recurringSeriesService->cancel($series, $request->user());

        Log::info('Admin cancelled recurring series future occurrences', [
            'series_id' => $id,
            'actor_id' => $request->user()->id,
            'lifecycle' => $lifecycle,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recurring series future occurrences processed successfully',
            'lifecycle' => $lifecycle,
        ]);
    }

    /**
     * POST /api/admin/recurring-series/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $series = RecurringSeries::findOrFail($id);
        $result = $this->adminRecurringSeriesService->approve($series, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Recurring series approved successfully',
            'data' => new AdminRecurringSeriesResource(
                $series->fresh(['organizer', 'creator', 'approver'])->loadCount('events'),
            ),
            'instances_approved' => $result['instances_approved'],
            'instances_published' => $result['instances_published'],
        ]);
    }

    /**
     * POST /api/admin/recurring-series/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $series = RecurringSeries::findOrFail($id);
        $result = $this->adminRecurringSeriesService->reject($series, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Recurring series rejected successfully',
            'data' => new AdminRecurringSeriesResource(
                $series->fresh(['organizer', 'creator'])->loadCount('events'),
            ),
            'instances_unapproved' => $result['instances_unapproved'],
        ]);
    }

    /**
     * POST /api/admin/recurring-series/{id}/unapprove
     */
    public function unapprove(Request $request, int $id): JsonResponse
    {
        return $this->reject($request, $id);
    }

    /**
     * POST /api/admin/recurring-series/{id}/suspend
     */
    public function suspend(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:1000']);

        $series = RecurringSeries::findOrFail($id);
        $result = $this->adminRecurringSeriesService->suspendSeries(
            $series,
            $request->user(),
            $request->input('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Recurring series suspended successfully',
            'data' => new AdminRecurringSeriesResource(
                $series->fresh(['organizer', 'creator'])->loadCount('events'),
            ),
            'suspended' => $result['suspended'],
        ]);
    }

    /**
     * POST /api/admin/recurring-series/{id}/unsuspend
     */
    public function unsuspend(Request $request, int $id): JsonResponse
    {
        $series = RecurringSeries::findOrFail($id);
        $result = $this->adminRecurringSeriesService->unsuspendSeries($series, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Recurring series unsuspended successfully',
            'data' => new AdminRecurringSeriesResource(
                $series->fresh(['organizer', 'creator'])->loadCount('events'),
            ),
            'unsuspended' => $result['unsuspended'],
        ]);
    }
}
