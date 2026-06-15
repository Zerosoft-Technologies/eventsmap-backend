<?php

namespace App\Services\V2;

use App\Helpers\MediaHelper;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * API-safe invited user shape (shared by {@see \App\Http\Resources\V2\InvitedUserResource} and bulk hydration).
 */
final class InvitedUserPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(User $user): array
    {
        $profileImage = null;
        if (Schema::hasColumn('users', 'profile_image_path')) {
            $path = $user->profile_image_path ?? null;
            if (is_string($path) && $path !== '') {
                $profileImage = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                    ? $path
                    : MediaHelper::url($path);
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),

            'profile_type' => $user->profile_type,
            'account_type' => $user->account_type,
            'status' => $user->status,
            'billing_type' => $user->billing_type,

            'full_name' => $user->full_name,
            'company_name' => $user->company_name,
            'vat_number' => $user->vat_number,
            'vat_validated' => (bool) ($user->vat_validated ?? false),

            'country' => $user->country,
            'address' => $user->address,
            'postal_code' => $user->postal_code,
            'city' => $user->city,

            'premium_started_at' => Schema::hasColumn('users', 'premium_started_at')
                ? ($user->premium_started_at instanceof \Carbon\Carbon
                    ? $user->premium_started_at->toIso8601String()
                    : $user->premium_started_at)
                : null,

            'profile_image' => $profileImage,
        ];
    }
}
