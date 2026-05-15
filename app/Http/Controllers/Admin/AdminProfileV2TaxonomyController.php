<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganiserCategory;
use App\Models\OrganiserSubcategory;
use App\Models\TalentCategory;
use App\Models\TalentSubcategory;
use App\Models\VenueCategory;
use App\Models\VenueSubcategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProfileV2TaxonomyController extends Controller
{
    private const PROFILES = ['talent', 'organiser', 'venue'];

    /**
     * GET /api/admin/v2/profile-taxonomies/{profile}
     */
    public function index(string $profile): JsonResponse
    {
        $this->assertProfile($profile);
        $categories = $this->categoryQuery($profile)
            ->with([
                $this->subRelationName($profile) => fn ($q) => $q->orderBy('name')->orderBy('id'),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories->map(fn ($c) => $this->serializeCategory($c, $profile)),
        ]);
    }

    /**
     * POST /api/admin/v2/profile-taxonomies/{profile}/categories
     */
    public function storeCategory(Request $request, string $profile): JsonResponse
    {
        $this->assertProfile($profile);

        $validated = $request->validate([
            'name' => 'required|string|min:1|max:255',
            'slug' => 'nullable|string|regex:/^[a-z0-9-]+$/|max:255',
        ]);

        $baseSlug = $validated['slug'] ?? Str::slug($validated['name']);
        $slug = $this->ensureUniqueCategorySlug($profile, $baseSlug, null);

        $modelClass = $this->categoryModelClass($profile);
        $category = $modelClass::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        $category->load($this->subRelationName($profile));

        return response()->json([
            'success' => true,
            'data' => $this->serializeCategory($category, $profile),
        ], 201);
    }

    /**
     * GET /api/admin/v2/profile-taxonomies/{profile}/categories/{categoryId}
     */
    public function showCategory(string $profile, int $categoryId): JsonResponse
    {
        $this->assertProfile($profile);
        $category = $this->categoryQuery($profile)
            ->with([$this->subRelationName($profile) => fn ($q) => $q->orderBy('name')->orderBy('id')])
            ->findOrFail($categoryId);

        return response()->json([
            'success' => true,
            'data' => $this->serializeCategory($category, $profile),
        ]);
    }

    /**
     * PUT /api/admin/v2/profile-taxonomies/{profile}/categories/{categoryId}
     */
    public function updateCategory(Request $request, string $profile, int $categoryId): JsonResponse
    {
        $this->assertProfile($profile);
        $category = $this->categoryQuery($profile)->findOrFail($categoryId);

        $table = $this->categoryTable($profile);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:1|max:255',
            'slug' => [
                'nullable',
                'string',
                'regex:/^[a-z0-9-]+$/',
                'max:255',
                Rule::unique($table, 'slug')->ignore($category->id),
            ],
        ]);

        if (array_key_exists('name', $validated)) {
            $category->name = $validated['name'];
        }

        if (array_key_exists('slug', $validated) && $validated['slug'] !== null && $validated['slug'] !== '') {
            $category->slug = $validated['slug'];
        } elseif (array_key_exists('name', $validated) && ! array_key_exists('slug', $validated)) {
            $category->slug = $this->ensureUniqueCategorySlug(
                $profile,
                Str::slug($category->name),
                $category->id
            );
        }

        $category->save();
        $category->load($this->subRelationName($profile));

        return response()->json([
            'success' => true,
            'data' => $this->serializeCategory($category, $profile),
        ]);
    }

    /**
     * DELETE /api/admin/v2/profile-taxonomies/{profile}/categories/{categoryId}
     */
    public function destroyCategory(string $profile, int $categoryId): JsonResponse
    {
        $this->assertProfile($profile);
        $category = $this->categoryQuery($profile)->findOrFail($categoryId);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted.',
        ]);
    }

    /**
     * POST /api/admin/v2/profile-taxonomies/{profile}/categories/{categoryId}/subcategories
     */
    public function storeSubcategory(Request $request, string $profile, int $categoryId): JsonResponse
    {
        $this->assertProfile($profile);
        $this->categoryQuery($profile)->findOrFail($categoryId);

        $validated = $request->validate([
            'name' => 'required|string|min:1|max:255',
            'slug' => 'nullable|string|regex:/^[a-z0-9-]+$/|max:255',
        ]);

        $fk = $this->parentFkOnSub($profile);
        $baseSlug = $validated['slug'] ?? Str::slug($validated['name']);
        $slug = $this->ensureUniqueSubcategorySlug($profile, $categoryId, $baseSlug, null);

        $modelClass = $this->subcategoryModelClass($profile);
        $sub = $modelClass::query()->create([
            $fk => $categoryId,
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->serializeSubcategory($sub, $profile),
        ], 201);
    }

    /**
     * PUT /api/admin/v2/profile-taxonomies/{profile}/subcategories/{subcategoryId}
     */
    public function updateSubcategory(Request $request, string $profile, int $subcategoryId): JsonResponse
    {
        $this->assertProfile($profile);
        $sub = $this->subcategoryQuery($profile)->findOrFail($subcategoryId);

        $categoryId = (int) $sub->getAttribute($this->parentFkOnSub($profile));
        $table = $this->subcategoryTable($profile);
        $fk = $this->parentFkOnSub($profile);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:1|max:255',
            'slug' => [
                'nullable',
                'string',
                'regex:/^[a-z0-9-]+$/',
                'max:255',
                Rule::unique($table, 'slug')
                    ->where(fn ($q) => $q->where($fk, $categoryId))
                    ->ignore($sub->id),
            ],
        ]);

        if (array_key_exists('name', $validated)) {
            $sub->name = $validated['name'];
        }

        if (array_key_exists('slug', $validated) && $validated['slug'] !== null && $validated['slug'] !== '') {
            $sub->slug = $validated['slug'];
        } elseif (array_key_exists('name', $validated) && ! array_key_exists('slug', $validated)) {
            $sub->slug = $this->ensureUniqueSubcategorySlug(
                $profile,
                $categoryId,
                Str::slug($sub->name),
                $sub->id
            );
        }

        $sub->save();

        return response()->json([
            'success' => true,
            'data' => $this->serializeSubcategory($sub, $profile),
        ]);
    }

    /**
     * DELETE /api/admin/v2/profile-taxonomies/{profile}/subcategories/{subcategoryId}
     */
    public function destroySubcategory(string $profile, int $subcategoryId): JsonResponse
    {
        $this->assertProfile($profile);
        $sub = $this->subcategoryQuery($profile)->findOrFail($subcategoryId);
        $sub->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subcategory deleted.',
        ]);
    }

    private function assertProfile(string $profile): void
    {
        if (! in_array($profile, self::PROFILES, true)) {
            abort(404, 'Invalid profile type. Use: talent, organiser, or venue.');
        }
    }

    /**
     * @return class-string<TalentCategory|OrganiserCategory|VenueCategory>
     */
    private function categoryModelClass(string $profile): string
    {
        return match ($profile) {
            'talent' => TalentCategory::class,
            'organiser' => OrganiserCategory::class,
            'venue' => VenueCategory::class,
        };
    }

    /**
     * @return class-string<TalentSubcategory|OrganiserSubcategory|VenueSubcategory>
     */
    private function subcategoryModelClass(string $profile): string
    {
        return match ($profile) {
            'talent' => TalentSubcategory::class,
            'organiser' => OrganiserSubcategory::class,
            'venue' => VenueSubcategory::class,
        };
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<TalentCategory|OrganiserCategory|VenueCategory>
     */
    private function categoryQuery(string $profile)
    {
        return $this->categoryModelClass($profile)::query();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<TalentSubcategory|OrganiserSubcategory|VenueSubcategory>
     */
    private function subcategoryQuery(string $profile)
    {
        return $this->subcategoryModelClass($profile)::query();
    }

    private function subRelationName(string $profile): string
    {
        return 'subcategories';
    }

    private function parentFkOnSub(string $profile): string
    {
        return match ($profile) {
            'talent' => 'talent_category_id',
            'organiser' => 'organiser_category_id',
            'venue' => 'venue_category_id',
        };
    }

    private function categoryTable(string $profile): string
    {
        return match ($profile) {
            'talent' => 'talent_categories',
            'organiser' => 'organiser_categories',
            'venue' => 'venue_categories',
        };
    }

    private function subcategoryTable(string $profile): string
    {
        return match ($profile) {
            'talent' => 'talent_subcategories',
            'organiser' => 'organiser_subcategories',
            'venue' => 'venue_subcategories',
        };
    }

    private function ensureUniqueCategorySlug(string $profile, string $baseSlug, ?int $ignoreId): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'category';
        $counter = 1;

        while ($this->categorySlugTaken($profile, $slug, $ignoreId)) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }

    private function categorySlugTaken(string $profile, string $slug, ?int $ignoreId): bool
    {
        $q = $this->categoryQuery($profile)->where('slug', $slug);
        if ($ignoreId !== null) {
            $q->where('id', '!=', $ignoreId);
        }

        return $q->exists();
    }

    private function ensureUniqueSubcategorySlug(string $profile, int $categoryId, string $baseSlug, ?int $ignoreSubId): string
    {
        $fk = $this->parentFkOnSub($profile);
        $slug = $baseSlug !== '' ? $baseSlug : 'subcategory';
        $counter = 1;

        while ($this->subcategorySlugTaken($profile, $categoryId, $slug, $ignoreSubId)) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }

    private function subcategorySlugTaken(string $profile, int $categoryId, string $slug, ?int $ignoreSubId): bool
    {
        $fk = $this->parentFkOnSub($profile);
        $q = $this->subcategoryQuery($profile)
            ->where($fk, $categoryId)
            ->where('slug', $slug);
        if ($ignoreSubId !== null) {
            $q->where('id', '!=', $ignoreSubId);
        }

        return $q->exists();
    }

    private function serializeCategory(Model $category, string $profile): array
    {
        $relation = $this->subRelationName($profile);
        $subs = $category->relationLoaded($relation)
            ? $category->getRelation($relation)
            : collect();

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'profile' => $profile,
            'subcategories' => $subs->map(fn ($s) => $this->serializeSubcategory($s, $profile))->values()->all(),
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }

    private function serializeSubcategory(Model $sub, string $profile): array
    {
        return [
            'id' => $sub->id,
            'name' => $sub->name,
            'slug' => $sub->slug,
            'profile' => $profile,
            $this->parentFkOnSub($profile) => $sub->getAttribute($this->parentFkOnSub($profile)),
            'created_at' => $sub->created_at?->toIso8601String(),
            'updated_at' => $sub->updated_at?->toIso8601String(),
        ];
    }
}
