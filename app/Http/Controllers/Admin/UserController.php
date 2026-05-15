<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * GET /api/admin/backoffice-users
     *
     * Paginated list of admin and super_admin accounts (super-admin panel).
     */
    public function backofficeIndex(Request $request): JsonResponse
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

        $query = User::query()->admins();

        if ($request->boolean('trashed')) {
            $query->withTrashed();
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $op = $query->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'like';
            $query->where(function ($q) use ($search, $op) {
                $q->where('name', $op, "%{$search}%")
                    ->orWhere('email', $op, "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

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
        $allowedSorts = ['name', 'email', 'created_at', 'role', 'is_active'];
        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = (int) $request->input('per_page', 20);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'email_verified' => $user->hasVerifiedEmail(),
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
     * GET /api/admin/backoffice-users/{id} | GET /api/admin/users/{id} (super_admin)
     *
     * Admin / super_admin account detail only.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::query()->admins()->withTrashed()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'email_verified' => $user->hasVerifiedEmail(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'profile_type' => $user->profile_type,
                'account_type' => $user->account_type,
                'status' => $user->status,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'deleted_at' => $user->deleted_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/admin/backoffice-users | POST /api/admin/users (super_admin)
     *
     * Create a new admin or super_admin user.
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
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,' . $id,
            'role'      => 'required|string|in:admin,super_admin',
            'password'  => ['nullable', Password::defaults()],
            'is_active' => 'nullable|boolean',
        ]);

        if ($err = $this->guardRoleDemotion($user, $validated['role'])) {
            return $err;
        }

        if (array_key_exists('is_active', $validated)
            && $validated['is_active'] === false
            && $user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'SELF_DEACTIVATE',
                    'message' => 'You cannot deactivate your own account.',
                ],
            ], 400);
        }

        // Only hash & keep password if it was actually provided
        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->role,
                'is_active'  => $user->is_active,
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * PATCH /api/admin/backoffice-users/{id}
     *
     * Partial update (super-admin panel).
     */
    public function partialUpdate(Request $request, int $id): JsonResponse
    {
        $user = User::admins()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($id)],
            'role' => 'sometimes|required|string|in:admin,super_admin',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['role'])) {
            if ($err = $this->guardRoleDemotion($user, $validated['role'])) {
                return $err;
            }
        }

        if (array_key_exists('is_active', $validated)
            && $validated['is_active'] === false
            && $user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SELF_DEACTIVATE',
                    'message' => 'You cannot deactivate your own account.',
                ],
            ], 400);
        }

        $user->fill($validated);
        $user->save();

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

        // Prevent deleting the last super admin
        if ($user->isSuperAdmin() && ! $this->otherSuperAdminExistsExcluding($user->id)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LAST_SUPER_ADMIN',
                    'message' => 'Cannot delete the last super admin account.',
                ],
            ], 422);
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

    /**
     * Another super_admin row exists other than {@code $excludeUserId}.
     */
    private function otherSuperAdminExistsExcluding(int $excludeUserId): bool
    {
        return User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('id', '!=', $excludeUserId)
            ->exists();
    }

    /**
     * Block demoting the last super admin to admin.
     *
     * @return JsonResponse|null
     */
    private function guardRoleDemotion(User $user, string $newRole): ?JsonResponse
    {
        if (! $user->isSuperAdmin()) {
            return null;
        }

        if ($newRole !== User::ROLE_ADMIN) {
            return null;
        }

        if (! $this->otherSuperAdminExistsExcluding($user->id)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LAST_SUPER_ADMIN',
                    'message' => 'Cannot demote the last super admin to admin.',
                ],
            ], 422);
        }

        return null;
    }
}
