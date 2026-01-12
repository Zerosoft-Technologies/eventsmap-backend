<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * GET /api/admin/users
     *
     * List admin users with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'role' => 'nullable|string|in:admin,super_admin',
            'is_active' => 'nullable|boolean',
            'trashed' => 'nullable|boolean',
        ]);

        $query = User::admins();

        // Include trashed if requested
        if ($request->boolean('trashed')) {
            $query->withTrashed();
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Role filter
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        // Active filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sorting
        $sortField = 'created_at';
        $sortDirection = 'desc';
        if ($request->filled('sort')) {
            $sort = $request->input('sort');
            if (str_starts_with($sort, '-')) {
                $sortDirection = 'desc';
                $sortField = substr($sort, 1);
            } else {
                $sortDirection = 'asc';
                $sortField = $sort;
            }
        }
        $allowedSorts = ['name', 'email', 'created_at', 'role'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Pagination
        $perPage = $request->input('per_page', 20);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                    'deleted_at' => $user->deleted_at?->toIso8601String(),
                ];
            }),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'total_pages' => $users->lastPage(),
                'has_more' => $users->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /api/admin/users/{id}
     *
     * Get single admin user.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::admins()->withTrashed()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'deleted_at' => $user->deleted_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/admin/users
     *
     * Create a new admin user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::defaults()],
            'role' => 'required|string|in:admin,super_admin',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * PUT /api/admin/users/{id}
     *
     * Update an admin user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::admins()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|string|in:admin,super_admin',
            'is_active' => 'nullable|boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /api/admin/users/{id}
     *
     * Delete an admin user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::admins()->findOrFail($id);

        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SELF_DELETE',
                    'message' => 'You cannot delete your own account.',
                ],
            ], 400);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Admin user deleted successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/users/{id}/activate
     *
     * Activate an admin user.
     */
    public function activate(int $id): JsonResponse
    {
        $user = User::admins()->findOrFail($id);
        $user->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Admin user activated successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/users/{id}/deactivate
     *
     * Deactivate an admin user.
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $user = User::admins()->findOrFail($id);

        // Prevent self-deactivation
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SELF_DEACTIVATE',
                    'message' => 'You cannot deactivate your own account.',
                ],
            ], 400);
        }

        $user->update(['is_active' => false]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Admin user deactivated successfully.',
            ],
        ]);
    }

    /**
     * POST /api/admin/users/{id}/reset-password
     *
     * Force reset password for an admin user.
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::admins()->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Revoke all tokens to force re-login
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Password reset successfully. User will need to login again.',
            ],
        ]);
    }
}
