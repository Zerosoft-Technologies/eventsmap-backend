<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class VenueSubcategory extends Model
{
    use HasFactory;

    protected $table = 'venue_subcategories';

    protected $fillable = [
        'venue_category_id',
        'name',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'venue_category_id' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (VenueSubcategory $subcategory) {
            if (empty($subcategory->slug)) {
                $subcategory->slug = Str::slug($subcategory->name);
            }
        });
    }

    public function venueCategory(): BelongsTo
    {
        return $this->belongsTo(VenueCategory::class, 'venue_category_id');
    }

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(
            VenueV2::class,
            'venue_venue_subcategory',
            'subcategory_id',
            'venue_id'
        );
    }
}
