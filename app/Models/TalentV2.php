<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TalentV2 extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'talents_v2';

    protected $fillable = [
        'user_id',
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
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'fan_club_url',
        'nationality',
        'show_nationality',
        'age',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getSubcategoriesFromIdsAttribute()
    {
        if (empty($this->subcategory_ids)) {
            return collect([]);
        }

        return SubCategory::whereIn('id', $this->subcategory_ids)->get();
    }

    public function talentCategory(): BelongsTo
    {
        return $this->belongsTo(TalentCategory::class, 'talent_category_id');
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
}
