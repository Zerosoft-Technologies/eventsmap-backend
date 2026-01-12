<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/admin/auth/login
     *
     * Admin login endpoint.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Access denied. Admin privileges required.',
                ],
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_DISABLED',
                    'message' => 'Your account has been disabled.',
                ],
            ], 403);
        }

        // Delete existing tokens if not remembering
        if (!$request->boolean('remember')) {
            $user->tokens()->delete();
        }

        $expiresAt = $request->boolean('remember')
            ? now()->addDays(30)
            : now()->addHours(24);

        $token = $user->createToken('admin-token', ['admin'], $expiresAt);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/admin/auth/logout
     *
     * Admin logout endpoint.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Successfully logged out.',
            ],
        ]);
    }

    /**
     * POST /api/admin/auth/refresh
     *
     * Refresh the access token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $expiresAt = now()->addHours(24);
        $token = $user->createToken('admin-token', ['admin'], $expiresAt);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/admin/auth/me
     *
     * Get current authenticated admin info.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

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
        ]);
    }
}
