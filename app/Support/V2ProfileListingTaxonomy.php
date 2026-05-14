<?php

namespace App\Support;

use App\Models\Category;
use App\Models\OrganiserCategory;
use App\Models\OrganiserSubcategory;
use App\Models\OrganiserV2;
use App\Models\SubCategory;
use App\Models\TalentCategory;
use App\Models\TalentSubcategory;
use App\Models\TalentV2;
use App\Models\VenueCategory;
use App\Models\VenueSubcategory;
use App\Models\VenueV2;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Maps {@code category} / {@code subcategory} query slugs to the correct V2 profile taxonomy
 * (talent / organiser / venue tables), with fallback to legacy {@code categories} / {@code sub_categories}
 * when profiles still store {@code category_id} + JSON {@code subcategory_ids}.
 */
final class V2ProfileListingTaxonomy
{
    /**
     * Accepts repeated slugs from query strings such as {@code nightclub,concert-venue} or {@code night club , concert-venue }.
     *
     * @return list<string>
     */
    private static function parseSubcategorySlugsFromRequest(Request $request): array
    {
        if (! $request->filled('subcategory')) {
            return [];
        }

        $raw = trim((string) $request->input('subcategory'));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $slugs = [];
        foreach ($parts as $part) {
            $s = trim($part);
            if ($s !== '') {
                $slugs[] = $s;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @param  class-string<TalentV2|OrganiserV2|VenueV2>  $modelClass
     */
    public static function applyCategoryAndSubcategoryFilters(
        Builder $query,
        Request $request,
        string $modelClass,
        bool $venueSubcategoriesUseVenueTaxonomy,
    ): void {
        match ($modelClass) {
            TalentV2::class => self::applyForTalent($query, $request),
            OrganiserV2::class => self::applyForOrganiser($query, $request),
            VenueV2::class => self::applyForVenue($query, $request, $venueSubcategoriesUseVenueTaxonomy),
            default => self::applyGenericLegacy($query, $request),
        };
    }

    private static function applyForOrganiser(Builder $query, Request $request): void
    {
        $categorySlug = $request->filled('category') ? (string) $request->input('category') : null;
        $subSlugs = self::parseSubcategorySlugsFromRequest($request);

        $organiserCategory = $categorySlug !== null
            ? OrganiserCategory::query()->where('slug', $categorySlug)->first()
            : null;
        $genericCategory = $categorySlug !== null && $organiserCategory === null
            ? Category::query()->where('slug', $categorySlug)->first()
            : null;

        if ($categorySlug !== null) {
            if ($organiserCategory !== null) {
                $query->where('organiser_category_id', $organiserCategory->id);
            } elseif ($genericCategory !== null) {
                $query->where('category_id', $genericCategory->id);
            } else {
                $query->whereRaw('0 = 1');

                return;
            }
        } elseif ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($subSlugs === []) {
            return;
        }

        if ($organiserCategory !== null) {
            $subIds = OrganiserSubcategory::query()
                ->where('organiser_category_id', $organiserCategory->id)
                ->whereIn('slug', $subSlugs)
                ->pluck('id');
            if ($subIds->isEmpty()) {
                $query->whereRaw('0 = 1');

                return;
            }
            self::whereHasOrganiserSubcategories($query, $subIds->all());

            return;
        }

        if ($genericCategory !== null) {
            $subIds = SubCategory::query()
                ->where('category_id', $genericCategory->id)
                ->whereIn('slug', $subSlugs)
                ->pluck('id');
            self::applyJsonSubcategoryIdsContains($query, $subIds->all());

            return;
        }

        // Subcategory only (any organiser_subcategories.slug match)
        $subIds = OrganiserSubcategory::query()->whereIn('slug', $subSlugs)->pluck('id');
        if ($subIds->isEmpty()) {
            $query->whereRaw('0 = 1');

            return;
        }
        self::whereHasOrganiserSubcategories($query, $subIds->all());
    }

    /**
     * @param  list<int>  $ids
     */
    private static function whereHasOrganiserSubcategories(Builder $query, array $ids): void
    {
        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->whereHas('organiserSubcategories', function (Builder $q) use ($ids) {
            $q->whereIn('organiser_subcategories.id', $ids);
        });
    }

    private static function applyForTalent(Builder $query, Request $request): void
    {
        $categorySlug = $request->filled('category') ? (string) $request->input('category') : null;
        $subSlugs = self::parseSubcategorySlugsFromRequest($request);

        $talentCategory = $categorySlug !== null
            ? TalentCategory::query()->where('slug', $categorySlug)->first()
            : null;
        $genericCategory = $categorySlug !== null && $talentCategory === null
            ? Category::query()->where('slug', $categorySlug)->first()
            : null;

        if ($categorySlug !== null) {
            if ($talentCategory !== null) {
                $query->where('talent_category_id', $talentCategory->id);
            } elseif ($genericCategory !== null) {
                $query->where('category_id', $genericCategory->id);
            } else {
                $query->whereRaw('0 = 1');

                return;
            }
        } elseif ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($subSlugs === []) {
            return;
        }

        if ($talentCategory !== null) {
            $subIds = TalentSubcategory::query()
                ->where('talent_category_id', $talentCategory->id)
                ->whereIn('slug', $subSlugs)
                ->pluck('id');
            if ($subIds->isEmpty()) {
                $query->whereRaw('0 = 1');

                return;
            }
            self::whereHasTalentSubcategories($query, $subIds->all());

            return;
        }

        if ($genericCategory !== null) {
            $subIds = SubCategory::query()
                ->where('category_id', $genericCategory->id)
                ->whereIn('slug', $subSlugs)
                ->pluck('id');
            self::applyJsonSubcategoryIdsContains($query, $subIds->all());

            return;
        }

        $subIds = TalentSubcategory::query()->whereIn('slug', $subSlugs)->pluck('id');
        if ($subIds->isEmpty()) {
            $query->whereRaw('0 = 1');

            return;
        }
        self::whereHasTalentSubcategories($query, $subIds->all());
    }

    /**
     * @param  list<int>  $ids
     */
    private static function whereHasTalentSubcategories(Builder $query, array $ids): void
    {
        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->whereHas('talentSubcategories', function (Builder $q) use ($ids) {
            $q->whereIn('talent_subcategories.id', $ids);
        });
    }

    private static function applyForVenue(Builder $query, Request $request, bool $venueSubcategoriesUseVenueTaxonomy): void
    {
        $categorySlug = $request->filled('category') ? (string) $request->input('category') : null;
        $subSlugs = self::parseSubcategorySlugsFromRequest($request);

        $venueCategory = $categorySlug !== null
            ? VenueCategory::query()->where('slug', $categorySlug)->first()
            : null;
        $genericCategory = $categorySlug !== null && $venueCategory === null
            ? Category::query()->where('slug', $categorySlug)->first()
            : null;

        if ($categorySlug !== null) {
            if ($venueCategory !== null) {
                $query->where('venue_category_id', $venueCategory->id);
            } elseif ($genericCategory !== null) {
                $query->where('category_id', $genericCategory->id);
            } else {
                $query->whereRaw('0 = 1');

                return;
            }
        } elseif ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($subSlugs === []) {
            return;
        }

        if ($venueSubcategoriesUseVenueTaxonomy) {
            $subQ = VenueSubcategory::query()->whereIn('slug', $subSlugs);
            if ($venueCategory !== null) {
                $subQ->where('venue_category_id', $venueCategory->id);
            }
            $subIds = $subQ->pluck('id');
            if ($subIds->isEmpty()) {
                $query->whereRaw('0 = 1');

                return;
            }
            $query->whereHas('venueSubcategories', function (Builder $q) use ($subIds) {
                $q->whereIn('venue_subcategories.id', $subIds->all());
            });

            return;
        }

        if ($genericCategory !== null) {
            $subIds = SubCategory::query()
                ->where('category_id', $genericCategory->id)
                ->whereIn('slug', $subSlugs)
                ->pluck('id');
            self::applyJsonSubcategoryIdsContains($query, $subIds->all());

            return;
        }

        $subIds = VenueSubcategory::query()->whereIn('slug', $subSlugs)->pluck('id');
        if ($subIds->isEmpty()) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->whereHas('venueSubcategories', function (Builder $q) use ($subIds) {
            $q->whereIn('venue_subcategories.id', $subIds->all());
        });
    }

    private static function applyGenericLegacy(Builder $query, Request $request): void
    {
        $categorySlug = $request->filled('category') ? (string) $request->input('category') : null;
        $categoryFromSlug = $categorySlug !== null
            ? Category::query()->where('slug', $categorySlug)->first()
            : null;

        if ($request->filled('category')) {
            if ($categoryFromSlug !== null) {
                $query->where('category_id', $categoryFromSlug->id);
            } else {
                $query->whereRaw('0 = 1');

                return;
            }
        } elseif ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if (! $request->filled('subcategory')) {
            return;
        }

        $subSlugs = self::parseSubcategorySlugsFromRequest($request);
        if ($subSlugs === []) {
            return;
        }

        $subQuery = SubCategory::query()->whereIn('slug', $subSlugs);
        if ($request->filled('category') && $categoryFromSlug !== null) {
            $subQuery->where('category_id', $categoryFromSlug->id);
        }
        $subIds = $subQuery->pluck('id')->all();
        self::applyJsonSubcategoryIdsContains($query, $subIds);
    }

    /**
     * @param  list<int>  $subIds
     */
    private static function applyJsonSubcategoryIdsContains(Builder $query, array $subIds): void
    {
        if ($subIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->where(function (Builder $outer) use ($subIds) {
            $outer->where(function (Builder $inner) use ($subIds) {
                foreach ($subIds as $sid) {
                    $inner->orWhereJsonContains('subcategory_ids', (int) $sid);
                }
            });
        });
    }
}
