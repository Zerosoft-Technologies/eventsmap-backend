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
 * Map-oriented public profile feeds (approved + published), mirroring {@see PublicEventController::index}.
 */
class PublicProfileController extends Controller
{
    public function __construct(
        private readonly EventInvitationService $eventInvitationService
    ) {}

    public function talents(Request $request): JsonResponse
    {
        return $this->index(
            $request,
            TalentV2::class,
            ['category:id,name,slug', 'user:id,name'],
            TalentResource::class,
            'talents',
            'Talents fetched successfully',
            true,
            false,
        );
    }

    public function organisers(Request $request): JsonResponse
    {
        return $this->index(
            $request,
            OrganiserV2::class,
            ['category:id,name,slug', 'user:id,name'],
            OrganiserResource::class,
            'organisers',
            'Organisers fetched successfully',
            false,
            false,
        );
    }

    public function venues(Request $request): JsonResponse
    {
        return $this->index(
            $request,
            VenueV2::class,
            ['category:id,name,slug', 'user:id,name', 'venueCategory:id,name,slug', 'venueSubcategories:id,venue_category_id,name,slug'],
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
     * @param  list<string>  $with
     */
    private function index(
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

            'min_lat' => 'nullable|numeric|between:-90,90',
            'max_lat' => 'nullable|numeric|between:-90,90',
            'min_lng' => 'nullable|numeric|between:-180,180',
            'max_lng' => 'nullable|numeric|between:-180,180',

            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:0.1|max:500',
            'radius_km' => 'nullable|numeric|min:0.1|max:500',

            'category_id' => 'nullable|integer|exists:categories,id',
            'category' => 'nullable|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:255',
            'event_type' => 'nullable|string|in:free,premium',

            'sort' => 'nullable|string|in:created_at,title,distance',
            'order' => 'nullable|string|in:asc,desc',
        ] + V2ListingEventFilters::dateValidationRules() + (
            $applyEventSessionFilters ? V2ListingEventFilters::sessionValidationRules() : []
        ));

        /** @var Builder $query */
        $query = $modelClass::query()
            ->select($this->profileTable($modelClass).'.*')
            ->publicVisible()
            ->with($with);

        if ($request->filled(['min_lat', 'max_lat', 'min_lng', 'max_lng'])) {
            $query->withinBbox(
                (float) $request->input('min_lat'),
                (float) $request->input('max_lat'),
                (float) $request->input('min_lng'),
                (float) $request->input('max_lng')
            );
        }

        if ($request->filled(['lat', 'lng']) && ($request->filled('radius_km') || $request->filled('radius'))) {
            $lat = (float) $request->input('lat');
            $lng = (float) $request->input('lng');
            $radius = (float) ($request->input('radius_km') ?? $request->input('radius'));

            $query->withinRadius($lat, $lng, $radius)
                ->withDistance($lat, $lng);
        }

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

        $query->when($request->filled('event_type'), function ($q) use ($request) {
            $q->where('event_type', $request->input('event_type'));
        });

        if (! in_array($modelClass, [TalentV2::class, OrganiserV2::class], true)) {
            V2ListingEventFilters::applyMatchingEventsToProfileQuery(
                $query,
                $request,
                $modelClass,
                $applyEventSessionFilters,
            );
        }

        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'asc');

        if ($sort === 'distance' && $request->filled(['lat', 'lng']) && ($request->filled('radius_km') || $request->filled('radius'))) {
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
     * @param  class-string $modelClass
     */
    private function profileTable(string $modelClass): string
    {
        return (new $modelClass)->getTable();
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
     * GET /api/v2/public/talents/{id}
     */
    public function showTalent(int $id): JsonResponse
    {
        return $this->showProfile(TalentV2::class, TalentResource::class, $id, 'Talent fetched successfully');
    }

    /**
     * GET /api/v2/public/organisers/{id}
     */
    public function showOrganiser(int $id): JsonResponse
    {
        return $this->showProfile(OrganiserV2::class, OrganiserResource::class, $id, 'Organiser fetched successfully');
    }

    /**
     * GET /api/v2/public/venues/{id}
     */
    public function showVenue(int $id): JsonResponse
    {
        return $this->showProfile(VenueV2::class, VenueResource::class, $id, 'Venue fetched successfully');
    }

    /**
     * @param  class-string<TalentV2|OrganiserV2|VenueV2>  $modelClass
     * @param  class-string  $resourceClass
     */
    private function showProfile(string $modelClass, string $resourceClass, int $id, string $message): JsonResponse
    {
        /** @var TalentV2|OrganiserV2|VenueV2 $profile */
        $profile = $modelClass::query()
            ->publicVisible()
            ->with($this->detailRelationsFor($modelClass))
            ->findOrFail($id);

        $this->hydrateSubcategoriesForListing([$profile]);
        $receiverType = $this->invitationReceiverTypeForProfileModel($modelClass);
        if ($receiverType !== null) {
            $this->eventInvitationService->hydrateUpcomingAcceptedInvitationEventsOnProfiles([$profile], $receiverType);
            $this->eventInvitationService->hydratePastAcceptedInvitationEventsOnProfiles([$profile], $receiverType);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new $resourceClass($profile),
        ]);
    }

    /**
     * @param  class-string<TalentV2|OrganiserV2|VenueV2>  $modelClass
     * @return list<string>
     */
    private function detailRelationsFor(string $modelClass): array
    {
        return match ($modelClass) {
            TalentV2::class => ['category', 'user', 'talentCategory', 'talentSubcategories'],
            OrganiserV2::class => ['category', 'user', 'organiserCategory', 'organiserSubcategories'],
            VenueV2::class => ['category', 'user', 'venueCategory', 'venueSubcategories'],
            default => ['category', 'user'],
        };
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
