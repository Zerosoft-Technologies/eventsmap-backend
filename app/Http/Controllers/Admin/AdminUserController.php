<?php

namespace App\Http\Controllers\Admin;

use App\Events\Admin\UserStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUserListRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\User;
use App\Services\Admin\AdminUserStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserStatsService $statsService
    ) {}

    /**
     * GET /api/admin/users/stats
     *
     * Get user statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->statsService->getStats();

        return response()->json([
            'success' => true,
            'message' => 'User stats fetched successfully',
            'data' => $stats,
        ]);
    }

    /**
     * GET /api/admin/users
     *
     * List users with filtering and pagination.
     */
    public function index(AdminUserListRequest $request): JsonResponse
    {
        $query = User::query();

        // Search filter (name or email)
        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->input('search');
            $q->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        });

        // Account type filter
        $query->when($request->filled('account_type'), function ($q) use ($request) {
            $q->where('account_type', $request->input('account_type'));
        });

        // Status filter
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->input('status'));
        });

        // Profile type filter
        $query->when($request->filled('profile_type'), function ($q) use ($request) {
            $q->where('profile_type', $request->input('profile_type'));
        });

        // Country filter
        $query->when($request->filled('country'), function ($q) use ($request) {
            $q->where('country', $request->input('country'));
        });

        // Order by latest first
        $query->orderBy('created_at', 'desc');

        // Paginate
        $users = $query->paginate($request->perPage());

        return response()->json([
            'success' => true,
            'message' => 'Users fetched successfully',
            'data' => [
                'users' => AdminUserResource::collection($users->items()),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ],
        ]);
    }

    /**
     * PATCH /api/admin/users/{id}/status
     *
     * Update user status.
     */
    public function updateStatus(UpdateUserStatusRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Cannot update Super Admin account
        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update Super Admin account status',
            ], 403);
        }

        // Cannot suspend yourself
        if ($user->id === $request->user()->id && $request->input('status') === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot suspend your own account',
            ], 403);
        }

        $oldStatus = $user->status;
        $newStatus = $request->input('status');

        // No change needed
        if ($oldStatus === $newStatus) {
            return response()->json([
                'success' => true,
                'message' => 'User status already set to ' . $newStatus,
                'data' => [
                    'id' => $user->id,
                    'status' => $user->status,
                ],
            ]);
        }

        DB::beginTransaction();
        try {
            $user->status = $newStatus;
            $user->save();

            // Fire event for logging/notifications
            event(new UserStatusUpdated(
                $user,
                $oldStatus,
                $newStatus,
                $request->user()
            ));

            // Log admin activity
            Log::info('User status updated', [
                'user_id' => $user->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => [
                    'id' => $user->id,
                    'status' => $user->status,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update user status', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
            ], 500);
        }
    }
}
