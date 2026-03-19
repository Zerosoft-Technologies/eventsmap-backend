<?php

namespace App\Http\Resources\V2;

use App\Helpers\MediaHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * EventResource - JSON transformation for V2 Events.
 *
 * Formats event data for API responses including:
 * - Basic event info
 * - Relationships (category, subcategories, venue, organisers, talents)
 * - Computed status and overnight detection
 * - Admin moderation data (when applicable)
 * - Analytics counters
 */
class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'cover_image' => $this->image_path ? MediaHelper::url($this->image_path) : null,

            // Category
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),

            'subcategory_ids' => $this->subcategory_ids ?? [],
            'invited_talents' => $this->invited_talents ?? [],
            'invited_organisers' => $this->invited_organisers ?? [],
            'invited_venues' => $this->invited_venues ?? [],

            'subcategories' => $this->whenLoaded('subcategories', function () {
                return $this->subcategories->map(fn ($sc) => [
                    'id' => $sc->id,
                    'name' => $sc->name,
                    'slug' => $sc->slug,
                ]);
            }),

            // Date & Time
            'formatted_date' => $this->start_date?->format('Y-m-d') ?? $this->event_date?->format('Y-m-d'),
            'event_date' => $this->event_date?->format('Y-m-d'),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'start_datetime' => $this->start_datetime?->toIso8601String(),
            'end_datetime' => $this->end_datetime?->toIso8601String(),
            'is_overnight' => $this->is_overnight,

            // Location
            'address' => $this->address,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,

            // Event settings
            'dresscode' => $this->dress_code,
            'age_limit' => $this->age_limit,
            'entrance_status' => $this->entrance_status,

            // Status (stored + computed)
            'status' => $this->status,
            'computed_status' => $this->computed_status,

            // Venue
            'venue' => $this->whenLoaded('venue', function () {
                if (!$this->venue) return null;
                return [
                    'id' => $this->venue->id,
                    'name' => $this->venue->name,
                    'slug' => $this->venue->slug,
                    'address' => $this->venue->address,
                ];
            }),

            // Organisers
            'organisers' => $this->whenLoaded('organisers', function () {
                return $this->organisers->map(fn ($o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'email' => $o->email,
                ]);
            }),

            // Talents
            'talents' => $this->whenLoaded('talents', function () {
                return $this->talents->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'image' => $t->image,
                    'role' => $t->pivot->role ?? null,
                    'sort_order' => $t->pivot->sort_order ?? 0,
                ]);
            }),

            // Owner
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),

            // Package & Analytics
            'is_free_package' => $this->is_free_package,
            'view_count' => $this->view_count ?? 0,
            'like_count' => $this->like_count ?? 0,

            // Admin moderation (only included when fields exist)
            'is_approved' => $this->is_approved ?? false,
            'approved_at' => $this->when($this->approved_at, fn () => $this->approved_at?->toIso8601String()),
            'suspension_reason' => $this->when($this->status === 'suspended', $this->suspension_reason),
            'suspended_at' => $this->when($this->suspended_at, fn () => $this->suspended_at?->toIso8601String()),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->when($this->deleted_at, fn () => $this->deleted_at?->toIso8601String()),
        ];
    }
}
