<?php

namespace App\Services\V2;

use App\Models\OrganiserCategory;
use Illuminate\Database\Eloquent\Collection;

class OrganiserCategoryService
{
    public function getAll(): Collection
    {
        return OrganiserCategory::query()
            ->with([
                'subcategories' => function ($q) {
                    $q->select('id', 'organiser_category_id', 'name', 'slug')
                        ->orderBy('id');
                },
            ])
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);
    }
}
