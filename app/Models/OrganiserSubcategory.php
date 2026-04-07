<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrganiserSubcategory extends Model
{
    use HasFactory;

    protected $table = 'organiser_subcategories';

    protected $fillable = [
        'organiser_category_id',
        'name',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'organiser_category_id' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OrganiserSubcategory $subcategory) {
            if (empty($subcategory->slug)) {
                $subcategory->slug = Str::slug($subcategory->name);
            }
        });
    }

    public function organiserCategory(): BelongsTo
    {
        return $this->belongsTo(OrganiserCategory::class, 'organiser_category_id');
    }
}
