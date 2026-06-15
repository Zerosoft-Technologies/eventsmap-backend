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

class OrganiserV2 extends Model
{
    use HasFactory, HasV2GeoScopes, SoftDeletes;

    protected $table = 'organiser_v2';

    protected $fillable = [
        'user_id',
        'status',
        'publish_status',
        'title',
        'slug',
        'event_type',
        'category_id',
        'subcategory_ids',
        'organiser_category_id',
        'address',
        'latitude',
        'longitude',
        'image_path',
        'additional_images',
        'description',
        'contact_phone',
        'contact_email',
        'contact_website',
        'contact_box_message',
        'contact_box_design_message',
        'show_contact_box',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
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
            'additional_images' => 'array',
            'show_upcoming_events' => 'boolean',
            'show_past_events' => 'boolean',
            'show_contact_box' => 'boolean',
            'show_photo_map_marker' => 'boolean',
            'is_approved' => 'boolean',
        ];
    }

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // ──────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────

    /**
     * Get subcategories based on the subcategory_ids column.
     */
    public function getSubcategoriesFromIdsAttribute()
    {
        if (empty($this->subcategory_ids)) {
            return collect([]);
        }

        return SubCategory::whereIn('id', $this->subcategory_ids)->get();
    }

    public function organiserCategory(): BelongsTo
    {
        return $this->belongsTo(OrganiserCategory::class, 'organiser_category_id');
    }

    public function organiserSubcategories(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganiserSubcategory::class,
            'organiser_organiser_subcategory',
            'organiser_id',
            'subcategory_id'
        );
    }

    // ──────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────

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
