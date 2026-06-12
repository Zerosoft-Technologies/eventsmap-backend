<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\OrganiserResource;
use App\Http\Resources\V2\TalentResource;
use App\Http\Resources\V2\VenueResource;
use App\Models\EventInvitation;
use App\Models\OrganiserV2;
use App\Models\SubCategory;
use App\Models\TalentV2;
use App\Models\VenueV2;
use App\Support\V2ListingEventFilters;
use App\Support\V2ProfileListingTaxonomy;
use App\Services\V2\EventInvitationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Browse listings for V2 profiles (published), mirroring {@see EventController::index}.
 */
class ProfileListingController extends Controller
{
    public function __construct(
        private readonly EventInvitationService $eventInvitationService
    ) {}

    public function talents(Request $request): JsonResponse
    {
        return $this->listing(
            $request,
            TalentV2::class,
            ['category', 'user', 'talentCategory', 'talentSubcategories'],
            TalentResource::class,
            'talents',
            'Talents fetched successfully',
            true,
            false,
        );
    }

    public function organisers(Request $request): JsonResponse
    {
        return $this->listing(
            $request,
            OrganiserV2::class,
            ['category', 'user', 'organiserCategory', 'organiserSubcategories'],
            OrganiserResource::class,
            'organisers',
            'Organisers fetched successfully',
            false,
            false,
        );
    }

    public function venues(Request $request): JsonResponse
    {
        return $this->listing(
            $request,
            VenueV2::class,
            ['category', 'user', 'venueCategory', 'venueSubcategories'],
            VenueResource::class,
            'venues',
            'Venues fetched successfully',
            false,
            true,
            true,
        );
    }

    /**
     * @param  class-string<TalentV2|OrganiserV2|VenueV2>  $modelClass
     * @param  class-string  $resourceClass
     */
    private function listing(
        Request $request,
        string $modelClass,
        array $with,
        string $resourceClass,
        string $dataKey,
        string $message,
        bool $includeCityInSearch,
        bool $filterSubcategoriesViaVenueTaxonomy = false,
        bool $applyEventSessionFilters = false,
    ): JsonResponse {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'category' => 'nullable|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:draft,upcoming,completed,suspended,cancelled',
            'event_type' => 'nullable|string|in:free,premium',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:500',
            'radius_km' => 'nullable|numeric|min:0.1|max:500',
            'sort' => 'nullable|string|in:created_at,title,distance',
            'order' => 'nullable|string|in:asc,desc',
        ] + V2ListingEventFilters::dateValidationRules() + (
            $applyEventSessionFilters ? V2ListingEventFilters::sessionValidationRules() : []
        ));

        /** @var Builder $query */
        $query = $modelClass::query()
            ->with($with)
            ->publishStatusPublished();

        $query->when($request->filled('search'), function ($q) use ($request, $includeCityInSearch) {
            $search = $request->input('search');
            $op = $q->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'like';
            $q->where(function ($sub) use ($search, $op, $includeCityInSearch) {
                $sub->where('title', $op, "%{$search}%")
                    ->orWhere('address', $op, "%{$search}%");
                if ($includeCityInSearch) {
                    $sub->orWhere('city', $op, "%{$search}%");
                }
            });
        });

        V2ProfileListingTaxonomy::applyCategoryAndSubcategoryFilters(
            $query,
            $request,
            $modelClass,
            $filterSubcategoriesViaVenueTaxonomy,
        );

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->input('status'));
        });

        $query->when($request->filled('event_type'), function ($q) use ($request) {
            $q->where('event_type', $request->input('event_type'));
        });

        V2ListingEventFilters::applyMatchingEventsToProfileQuery(
            $query,
            $request,
            $modelClass,
            $applyEventSessionFilters,
        );

        if ($request->filled(['lat', 'lng']) && ($request->filled('radius') || $request->filled('radius_km'))) {
            $lat = (float) $request->input('lat');
            $lng = (float) $request->input('lng');
            $radiusKm = (float) ($request->input('radius_km') ?? $request->input('radius'));

            $query->withinRadius($lat, $lng, $radiusKm)->withDistance($lat, $lng);
        }

        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');

        if ($request->filled(['lat', 'lng']) && ($request->filled('radius') || $request->filled('radius_km')) && ! $request->filled('sort')) {
            $query->orderBy('distance_km', 'asc');
        } elseif ($sort === 'distance' && $request->filled(['lat', 'lng']) && ($request->filled('radius') || $request->filled('radius_km'))) {
            $query->orderBy('distance_km', $order);
        } else {
            if ($sort === 'distance') {
                $query->orderBy('created_at', $order);
            } else {
                $query->orderBy($sort, $order);
            }
        }

        $perPage = (int) $request->input('per_page', 20);
        $paginator = $query->paginate($perPage);

        $items = $paginator->items();
        $this->hydrateSubcategoriesForListing($items);
        $receiverType = $this->invitationReceiverTypeForProfileModel($modelClass);
        if ($receiverType !== null) {
            $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles($items, $receiverType);
            $this->eventInvitationService->hydratePastAcceptedInvitationEventsOnProfiles($items, $receiverType);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                $dataKey => $resourceClass::collection($items),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * @param  array<int, Model>  $profiles
     */
    private function hydrateSubcategoriesForListing(array $profiles): void
    {
        if ($profiles === []) {
            return;
        }

        $neededIds = [];
        foreach ($profiles as $profile) {
            if (! $profile instanceof Model) {
                continue;
            }
            $ids = $profile->getAttribute('subcategory_ids');
            if (! is_array($ids) || $ids === []) {
                continue;
            }
            foreach ($ids as $id) {
                $neededIds[(int) $id] = true;
            }
        }

        if ($neededIds === []) {
            return;
        }

        $subsById = SubCategory::query()
            ->whereIn('id', array_keys($neededIds))
            ->get()
            ->keyBy('id');

        foreach ($profiles as $profile) {
            if (! $profile instanceof Model) {
                continue;
            }
            if ($profile->relationLoaded('subcategories') && $profile->subcategories !== null && $profile->subcategories->isNotEmpty()) {
                continue;
            }
            $ids = $profile->getAttribute('subcategory_ids');
            if (! is_array($ids) || $ids === []) {
                continue;
            }
            $collection = collect($ids)
                ->map(fn ($id) => $subsById->get((int) $id))
                ->filter()
                ->values();
            if ($collection->isNotEmpty()) {
                $profile->setRelation('subcategories', $collection);
            }
        }
    }

    /**
     * @param  class-string $modelClass
     */
    private function invitationReceiverTypeForProfileModel(string $modelClass): ?string
    {
        return match ($modelClass) {
            TalentV2::class => EventInvitation::TYPE_TALENT,
            OrganiserV2::class => EventInvitation::TYPE_ORGANISER,
            VenueV2::class => EventInvitation::TYPE_VENUE,
            default => null,
        };
    }
}
