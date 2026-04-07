<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrganiserCategory extends Model
{
    use HasFactory;

    protected $table = 'organiser_categories';

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OrganiserCategory $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(OrganiserSubcategory::class, 'organiser_category_id');
    }
}
