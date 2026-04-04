<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VenueV2 extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'venue_v2';

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'event_type',
        'category_id',
        'subcategory_ids',
        'address',
        'latitude',
        'longitude',
        'image_path',
        'additional_images',
        'description',
        'allow_dogs',
        'wheelchair_accessible',
        'parking',
        'valet',
        'play_area',
        'contact_phone',
        'contact_email',
        'contact_website',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'opening_hours',
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
            'opening_hours' => 'array',
            'allow_dogs' => 'boolean',
            'wheelchair_accessible' => 'boolean',
            'parking' => 'boolean',
            'valet' => 'boolean',
            'play_area' => 'boolean',
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

    public function isOwner(?User $user): bool
    {
        return $user && $this->user_id === $user->id;
    }
}
