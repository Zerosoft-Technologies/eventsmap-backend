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

class TalentV2 extends Model
{
    use HasFactory, HasV2GeoScopes, SoftDeletes;

    protected $table = 'talents_v2';

    protected $fillable = [
        'user_id',
        'status',
        'publish_status',
        'title',
        'slug',
        'event_type',
        'category_id',
        'subcategory_ids',
        'talent_category_id',
        'city',
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
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'fan_club_url',
        'nationality',
        'show_nationality',
        'age',
        'date_of_birth',
        'show_age',
        'languages',
        'highlights',
        'show_upcoming_events',
        'show_past_events',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'subcategory_ids' => 'array',
            'additional_images' => 'array',
            'date_of_birth' => 'date',
            'languages' => 'array',
            'show_upcoming_events' => 'boolean',
            'show_past_events' => 'boolean',
            'is_approved' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Legacy event taxonomy FK (references {@see Category}).
     */
    public function eventCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Preferred talent taxonomy (references {@see TalentCategory}); uses {@code talent_category_id}.
     */
    public function talentTaxonomyCategory(): BelongsTo
    {
        return $this->belongsTo(TalentCategory::class, 'talent_category_id');
    }

    /**
     * @deprecated Use {@see talentTaxonomyCategory()} via {@see talentCategory()} alias.
     * Kept because many eager-load calls use {@code category}; points to talent_categories.
     */
    public function category(): BelongsTo
    {
        return $this->talentTaxonomyCategory();
    }

    public function getSubcategoriesFromIdsAttribute()
    {
        if (empty($this->subcategory_ids)) {
            return collect([]);
        }

        return TalentSubcategory::whereIn('id', $this->subcategory_ids)->get();
    }

    public function talentCategory(): BelongsTo
    {
        return $this->talentTaxonomyCategory();
    }

    public function talentSubcategories(): BelongsToMany
    {
        return $this->belongsToMany(
            TalentSubcategory::class,
            'talent_talent_subcategory',
            'talent_id',
            'subcategory_id'
        );
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
}
