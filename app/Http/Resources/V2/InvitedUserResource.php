<?php

namespace App\Http\Resources\V2;

use App\Helpers\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

/**
 * Full user profile from `users` for invited talents / organisers (API-safe, no billing secrets).
 */
class InvitedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profileImage = null;
        if (Schema::hasColumn('users', 'profile_image')) {
            $path = $this->profile_image ?? null;
            if (is_string($path) && $path !== '') {
                $profileImage = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                    ? $path
                    : MediaHelper::url($path);
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_active' => (bool) $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),

            'profile_type' => $this->profile_type,
            'account_type' => $this->account_type,
            'status' => $this->status,
            'billing_type' => $this->billing_type,

            'full_name' => $this->full_name,
            'company_name' => $this->company_name,
            'vat_number' => $this->vat_number,
            'vat_validated' => (bool) ($this->vat_validated ?? false),

            'country' => $this->country,
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'city' => $this->city,

            'premium_started_at' => Schema::hasColumn('users', 'premium_started_at')
                ? $this->premium_started_at?->toIso8601String()
                : null,

            'profile_image' => $profileImage,
        ];
    }
}
