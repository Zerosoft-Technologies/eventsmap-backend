<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TalentSubcategory extends Model
{
    use HasFactory;

    protected $table = 'talent_subcategories';

    protected $fillable = [
        'talent_category_id',
        'name',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'talent_category_id' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (TalentSubcategory $subcategory) {
            if (empty($subcategory->slug)) {
                $subcategory->slug = Str::slug($subcategory->name);
            }
        });
    }

    public function talentCategory(): BelongsTo
    {
        return $this->belongsTo(TalentCategory::class, 'talent_category_id');
    }
}
