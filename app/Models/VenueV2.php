<?php

namespace App\Models;

use App\Models\Concerns\HasV2GeoScopes;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VenueV2 extends Model
{
    use HasFactory, HasV2GeoScopes, SoftDeletes;

    protected $table = 'venue_v2';

    protected $fillable = [
        'user_id',
        'status',
        'publish_status',
        'title',
        'slug',
        'event_type',
        'category_id',
        'subcategory_ids',
        'venue_category_id',
        'address',
        'latitude',
        'longitude',
        'image_path',
        'additional_images',
        'description',
        'description_items',
        'allow_dogs',
        'allowance_of_dogs',
        'wheelchair_accessible',
        'accessibility_description',
        'parking',
        'valet',
        'play_area',
        'contact_phone',
        'contact_email',
        'contact_website',
        'contact_box_message',
        'contact_box_design_message',
        'show_contact_box',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'opening_hours',
        'show_upcoming_events',
        'show_past_events',
        'show_photo_map_marker',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'subcategory_ids' => 'array',
            'description_items' => 'array',
            'additional_images' => 'array',
            'opening_hours' => 'array',
            'allow_dogs' => 'boolean',
            'wheelchair_accessible' => 'boolean',
            'parking' => 'boolean',
            'valet' => 'boolean',
            'play_area' => 'boolean',
            'show_upcoming_events' => 'boolean',
            'show_past_events' => 'boolean',
            'show_contact_box' => 'boolean',
            'show_photo_map_marker' => 'boolean',
            'is_approved' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function venueCategory(): BelongsTo
    {
        return $this->belongsTo(VenueCategory::class, 'venue_category_id');
    }

    public function venueSubcategories(): BelongsToMany
    {
        return $this->belongsToMany(
            VenueSubcategory::class,
            'venue_venue_subcategory',
            'venue_id',
            'subcategory_id'
        );
    }

    public function getSubcategoriesFromIdsAttribute()
    {
        if (empty($this->subcategory_ids)) {
            return collect([]);
        }

        return SubCategory::whereIn('id', $this->subcategory_ids)->get();
    }

    public function isOwner(?User $user): bool
    {
        return $user && $this->user_id === $user->id;
    }

    /**
     * Scope: only {@see PublishStatus::PUBLISHED} rows (public listings / embedded API payloads).
     */
    public function scopePublishStatusPublished(Builder $query): Builder
    {
        return $query->where('publish_status', PublishStatus::PUBLISHED);
    }

    /**
     * Scope for map / public directory: approved, published, not draft or blocked states.
     */
    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->publishStatusPublished()
            ->where('is_approved', true)
            ->whereNotIn('status', [
                ProfilePublicationStatus::DRAFT,
                ProfilePublicationStatus::SUSPENDED,
                ProfilePublicationStatus::CANCELLED,
            ]);
    }

    /**
     * Profiles selectable in the premium event invitation picker (matches browse listings).
     */
    public function scopeInvitableForEvent(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $visibility) {
                $visibility->where('publish_status', PublishStatus::PUBLISHED)
                    ->orWhereIn('status', [
                        ProfilePublicationStatus::UPCOMING,
                        ProfilePublicationStatus::COMPLETED,
                    ]);
            })
            ->where(function (Builder $statusQuery) {
                $statusQuery->whereNull('status')
                    ->orWhereNotIn('status', [
                        ProfilePublicationStatus::SUSPENDED,
                        ProfilePublicationStatus::CANCELLED,
                    ]);
            });
    }
}
