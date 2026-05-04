<?php

namespace App\Http\Controllers;

use App\Helpers\MediaHelper;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserProfileController extends Controller
{
    /**
     * PUT /api/user/profile
     *
     * Update the authenticated user's profile (multipart file fields profile_image or avatar: JPEG/PNG/WebP, max 5MB).
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validated();

        $updateData = [];

        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }

        if (isset($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
            $user->tokens()->delete();
        }

        if (isset($validated['billing_type'])) {
            $updateData['billing_type'] = $validated['billing_type'];
        }

        if (isset($validated['company_name'])) {
            $updateData['company_name'] = $validated['company_name'];
        }

        if (isset($validated['vat_number'])) {
            $updateData['vat_number'] = $validated['vat_number'];
            if (! $user->vat_validated) {
                $updateData['vat_validated'] = false;
            }
        }

        if (isset($validated['address'])) {
            $updateData['address'] = $validated['address'];
        }

        if (isset($validated['country'])) {
            $updateData['country'] = $validated['country'];
        }

        $profileImageChanged = false;

        if ($request->boolean('remove_profile_image')) {
            $this->deleteStoredProfileImage($user->profile_image_path);
            $updateData['profile_image_path'] = null;
            $profileImageChanged = true;
        } elseif ($upload = $request->file('profile_image') ?? $request->file('avatar')) {
            $this->deleteStoredProfileImage($user->profile_image_path);
            $updateData['profile_image_path'] = $this->storeProfileImage($user, $upload);
            $profileImageChanged = true;
        }

        if ($updateData === [] && ! $profileImageChanged) {
            return response()->json([
                'success' => true,
                'message' => 'No changes to update.',
                'data' => [
                    'user' => $this->formatUser($user),
                ],
            ]);
        }

        try {
            $user->update($updateData);
            $user->refresh();

            Log::info('User profile updated', [
                'user_id' => $user->id,
                'updated_fields' => array_keys($updateData),
                'ip' => $request->ip(),
            ]);

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

    private function storeProfileImage(User $user, UploadedFile $image): string
    {
        $extension = strtolower((string) ($image->getClientOriginalExtension() ?: $image->guessExtension() ?: 'jpg'));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        if ($extension === '') {
            $extension = 'jpg';
        }

        $filename = Str::uuid()->toString().'.'.$extension;

        $path = $image->storeAs('profiles/'.$user->id, $filename, 'public');
        if ($path === false) {
            throw new \RuntimeException('Failed to store profile image.');
        }

        return $path;
    }

    private function deleteStoredProfileImage(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
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
            'profile_image_path' => $user->profile_image_path,
            'profile_image_url' => MediaHelper::resolveUrl($user->profile_image_path),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
