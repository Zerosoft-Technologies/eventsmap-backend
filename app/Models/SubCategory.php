<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SubCategory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'subcategories';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'slug',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Generate slug from name when creating
        static::creating(function (SubCategory $subCategory) {
            if (empty($subCategory->slug)) {
                $subCategory->slug = Str::slug($subCategory->name);
            }
        });

        // Update slug when name changes
        static::updating(function (SubCategory $subCategory) {
            if ($subCategory->isDirty('name') && empty($subCategory->slug)) {
                $subCategory->slug = Str::slug($subCategory->name);
            }
        });
    }

    /**
     * Get the category that owns the subcategory.
     *
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Find subcategory by slug.
     *
     * @param string $slug
     * @return SubCategory|null
     */
    public static function findBySlug(string $slug): ?SubCategory
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Scope to get subcategories by category slug.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $categorySlug
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCategorySlug($query, string $categorySlug)
    {
        return $query->whereHas('category', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }
}
