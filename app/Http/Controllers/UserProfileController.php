<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserProfileController extends Controller
{
    /**
     * PUT /api/user/profile
     *
     * Update the authenticated user's profile.
     * Requires auth:sanctum middleware.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Get only the validated fields that were actually sent
        $validated = $request->validated();
        
        // Prepare update data
        $updateData = [];
        
        // Update name if provided
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        
        // Update password if provided
        if (isset($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
            // Invalidate all existing tokens to force re-login with new password
            $user->tokens()->delete();
        }
        
        // Update billing information if provided
        if (isset($validated['billing_type'])) {
            $updateData['billing_type'] = $validated['billing_type'];
        }
        
        if (isset($validated['company_name'])) {
            $updateData['company_name'] = $validated['company_name'];
        }
        
        // VAT number validation check is handled in the form request
        if (isset($validated['vat_number'])) {
            $updateData['vat_number'] = $validated['vat_number'];
            // Reset validation status if VAT number changes and wasn't previously validated
            if (!$user->vat_validated) {
                $updateData['vat_validated'] = false;
            }
        }
        
        if (isset($validated['address'])) {
            $updateData['address'] = $validated['address'];
        }
        
        if (isset($validated['country'])) {
            $updateData['country'] = $validated['country'];
        }
        
        // Only update if there's actually data to update
        if (empty($updateData)) {
            return response()->json([
                'success' => true,
                'message' => 'No changes to update.',
                'data' => [
                    'user' => $this->formatUser($user),
                ],
            ]);
        }
        
        try {
            // Update the user
            $user->update($updateData);
            
            // Log the update for audit purposes
            Log::info('User profile updated', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($updateData),
                'ip' => $request->ip(),
            ]);
            
            // Return the updated user data
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => [
                    'user' => $this->formatUser($user),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update user profile', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
    
    /**
     * Get user profile.
     * GET /api/user/profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUser($user),
            ],
        ]);
    }
    
    /**
     * Format user data for API response.
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_type' => $user->profile_type,
            'account_type' => $user->account_type,
            'role' => $user->role,
            'status' => $user->status,
            'is_active' => $user->is_active,
            'email_verified_at' => $user->email_verified_at,
            'premium_started_at' => $user->premium_started_at,
            'billing_type' => $user->billing_type,
            'full_name' => $user->full_name,
            'company_name' => $user->company_name,
            'vat_number' => $user->vat_number,
            'vat_validated' => $user->vat_validated,
            'address' => $user->address,
            'postal_code' => $user->postal_code,
            'city' => $user->city,
            'country' => $user->country,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
