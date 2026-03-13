<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    private const VALID_PROFILE_TYPES = ['organizer', 'talent', 'venue'];

    /**
     * GET /api/v2/users
     *
     * List users filtered by profile_type.
     * Optional query param: ?profile_type=talent
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'profile_type' => 'nullable|string|in:organiser,talent,venue',
            ]);

            $query = User::query()
                ->select(['id', 'name', 'profile_type', 'account_type', 'country'])
                ->where('account_type', 'premium');

            if ($request->filled('profile_type')) {
                $query->where('profile_type', $request->input('profile_type'));
            } else {
                $query->whereIn('profile_type', self::VALID_PROFILE_TYPES);
            }

            $users = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filter parameters.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('UserController@index failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users.',
            ], 500);
        }
    }
}
