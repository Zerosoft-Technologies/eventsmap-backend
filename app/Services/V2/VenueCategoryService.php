<?php

namespace App\Services\V2;

use App\Models\VenueCategory;
use Illuminate\Database\Eloquent\Collection;

class VenueCategoryService
{
    /**
     * All venue profile categories with nested venue subcategories (same pattern as {@see TalentCategoryService}).
     *
     * @return Collection<int, VenueCategory>
     */
    public function getAll(): Collection
    {
        return VenueCategory::query()
            ->with([
                'subcategories' => function ($q) {
                    $q->select('id', 'venue_category_id', 'name', 'slug')
                        ->orderBy('id');
                },
            ])
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);
    }
}
