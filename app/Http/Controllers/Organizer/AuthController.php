<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Organizer\OrganizerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/organizer/auth/login
     *
     * Login organizer and return token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user has organizer role
        if (!$user->hasRole('organizer') && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. User is not an organizer.',
            ], 403);
        }

        // Delete existing tokens
        $user->tokens()->delete();

        $token = $user->createToken('organizer-token', ['organizer']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new OrganizerResource($user),
                'token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
            ],
            'message' => 'Login successful',
        ]);
    }

    /**
     * POST /api/organizer/auth/logout
     *
     * Logout organizer (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * GET /api/organizer/auth/me
     *
     * Get current organizer user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new OrganizerResource($request->user()),
        ]);
    }

    /**
     * POST /api/organizer/auth/refresh
     *
     * Refresh token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Delete current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('organizer-token', ['organizer']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new OrganizerResource($user),
                'token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at,
            ],
            'message' => 'Token refreshed successfully',
        ]);
    }
}
