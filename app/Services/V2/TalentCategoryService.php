<?php

namespace App\Services\V2;

use App\Models\TalentCategory;
use Illuminate\Database\Eloquent\Collection;

class TalentCategoryService
{
    public function getAll(): Collection
    {
        return TalentCategory::query()
            ->with([
                'subcategories' => function ($q) {
                    $q->select('id', 'talent_category_id', 'name', 'slug')
                        ->orderBy('id');
                },
            ])
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);
    }
}
