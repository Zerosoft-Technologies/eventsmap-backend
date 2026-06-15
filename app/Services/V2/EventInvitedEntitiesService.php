<?php

namespace App\Services\V2;

use App\Http\Resources\V2\OrganiserResource;
use App\Http\Resources\V2\TalentResource;
use App\Http\Resources\V2\VenueResource;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\OrganiserV2;
use App\Models\TalentV2;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueV2;
use App\Support\V2ProfileCoverImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk-loads users and venues referenced by invited_* ID arrays on events.
 *
 * Invited talents / organisers resolve {@see TalentV2} / {@see OrganiserV2} by {@code user_id}. When several
 * V2 rows exist per user, the lowest {@code id} is used among published ({@see \App\Support\PublishStatus::PUBLISHED}) profiles only. Each invite object includes nested {@code talent_v2} /
 * {@code organiser_v2} (full API resource shape) when present.
 *
 * Invited venues use legacy {@see Venue}; {@see VenueV2} is resolved by the venue owner's {@code user_id} the same way.
 */
class EventInvitedEntitiesService
{
    /**
     * @param  iterable<int, EventV2>  $events
     */
    public function hydrate(iterable $events): void
    {
        $events = $events instanceof Collection ? $events : collect($events);
        if ($events->isEmpty()) {
            return;
        }

        $talentUserIds = [];
        $organiserUserIds = [];
        $venueIds = [];

        foreach ($events as $event) {
            foreach ($this->normalizeIdList($event->invited_talents ?? []) as $id) {
                $talentUserIds[$id] = true;
            }
            foreach ($this->normalizeIdList($event->invited_organisers ?? []) as $id) {
                $organiserUserIds[$id] = true;
            }
            foreach ($this->normalizeIdList($event->invited_venues ?? []) as $id) {
                $venueIds[$id] = true;
            }
        }

        $talentUserIds = array_keys($talentUserIds);
        $organiserUserIds = array_keys($organiserUserIds);
        $venueIds = array_keys($venueIds);

        $allInviteUserIds = array_values(array_unique(array_merge($talentUserIds, $organiserUserIds)));

        $usersById = $allInviteUserIds === []
            ? collect()
            : $this->loadUsersForInvites($allInviteUserIds);

        $talentsByUserId = $talentUserIds === []
            ? collect()
            : $this->loadTalentProfilesByUserId($talentUserIds);

        $organisersByUserId = $organiserUserIds === []
            ? collect()
            : $this->loadOrganiserProfilesByUserId($organiserUserIds);

        $venuesById = $venueIds === []
            ? collect()
            : Venue::query()->whereIn('id', $venueIds)->get()->keyBy('id');

        $venueOwnerUserIds = $venuesById->pluck('user_id')->unique()->filter()->values()->all();
        $venueV2ByUserId = $this->loadVenueV2FirstByUserId($venueOwnerUserIds);

        $eventIds = $events->pluck('id')->all();
        $acceptedByEvent = $this->loadAcceptedInvitesGrouped($eventIds);
        $invitationsByEvent = $this->loadAllInvitesGrouped($eventIds);

        foreach ($events as $event) {
            $accepted = $acceptedByEvent->get($event->id, collect());
            $eventInvitations = $invitationsByEvent->get($event->id, collect());
            $acceptedTalentUserIds = $this->acceptedReceiverIds($accepted, EventInvitation::TYPE_TALENT);
            $acceptedOrganiserUserIds = $this->acceptedReceiverIds($accepted, EventInvitation::TYPE_ORGANISER);
            $acceptedVenueUserIds = $this->acceptedReceiverIds($accepted, EventInvitation::TYPE_VENUE);

            $talentIds = $this->filterIdsByAccepted(
                $this->normalizeIdList($event->invited_talents ?? []),
                $acceptedTalentUserIds,
                $eventInvitations,
                EventInvitation::TYPE_TALENT,
            );
            $organiserIds = $this->filterIdsByAccepted(
                $this->normalizeIdList($event->invited_organisers ?? []),
                $acceptedOrganiserUserIds,
                $eventInvitations,
                EventInvitation::TYPE_ORGANISER,
            );
            $venueIdList = $this->filterVenueIdsByAccepted(
                $this->normalizeIdList($event->invited_venues ?? []),
                $venuesById,
                $acceptedVenueUserIds,
                $eventInvitations,
            );

            $event->setAttribute(
                'invited_talents_objects',
                $this->mapOrderedTalentInvites($usersById, $talentsByUserId, $talentIds)
            );
            $event->setAttribute(
                'invited_organisers_objects',
                $this->mapOrderedOrganiserInvites($usersById, $organisersByUserId, $organiserIds)
            );
            $event->setAttribute(
                'invited_venues_objects',
                $this->mapOrderedVenues($venuesById, $venueV2ByUserId, $venueIdList)
            );
        }
    }

    /**
     * @param  int[]  $eventIds
     * @return Collection<int, Collection<int, EventInvitation>>
     */
    private function loadAcceptedInvitesGrouped(array $eventIds): Collection
    {
        if ($eventIds === [] || ! Schema::hasTable('event_invitations')) {
            return collect();
        }

        return EventInvitation::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', EventInvitation::STATUS_ACCEPTED)
            ->get()
            ->groupBy('event_id');
    }

    /**
     * @param  int[]  $eventIds
     * @return Collection<int, Collection<int, EventInvitation>>
     */
    private function loadAllInvitesGrouped(array $eventIds): Collection
    {
        if ($eventIds === [] || ! Schema::hasTable('event_invitations')) {
            return collect();
        }

        return EventInvitation::query()
            ->whereIn('event_id', $eventIds)
            ->get()
            ->groupBy('event_id');
    }

    /**
     * @param  Collection<int, EventInvitation>  $accepted
     * @return int[]
     */
    private function acceptedReceiverIds(Collection $accepted, string $receiverType): array
    {
        return $accepted
            ->where('receiver_type', $receiverType)
            ->pluck('receiver_id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Only show invitees with an accepted invitation (pending/rejected stay hidden on public event view).
     *
     * @param  int[]  $orderedIds
     * @param  int[]  $acceptedUserIds
     * @param  Collection<int, EventInvitation>  $accepted
     * @return int[]
     */
    private function filterIdsByAccepted(
        array $orderedIds,
        array $acceptedUserIds,
        Collection $eventInvitations,
        string $receiverType,
    ): array {
        if ($orderedIds === []) {
            return [];
        }

        $hasInvitationsForType = $eventInvitations->contains(
            static fn (EventInvitation $inv) => $inv->receiver_type === $receiverType
        );

        if (! $hasInvitationsForType) {
            return $orderedIds;
        }

        $acceptedSet = array_fill_keys($acceptedUserIds, true);

        return array_values(array_filter($orderedIds, static fn (int $id) => isset($acceptedSet[$id])));
    }

    /**
     * @param  Collection<int, Venue>  $venuesById
     * @param  int[]  $orderedVenueIds
     * @param  int[]  $acceptedVenueOwnerUserIds
     * @param  Collection<int, EventInvitation>  $eventInvitations
     * @return int[]
     */
    private function filterVenueIdsByAccepted(
        array $orderedVenueIds,
        Collection $venuesById,
        array $acceptedVenueOwnerUserIds,
        Collection $eventInvitations,
    ): array {
        if ($orderedVenueIds === []) {
            return [];
        }

        $hasInvitationsForType = $eventInvitations->contains(
            static fn (EventInvitation $inv) => $inv->receiver_type === EventInvitation::TYPE_VENUE
        );

        if (! $hasInvitationsForType) {
            return $orderedVenueIds;
        }

        $acceptedOwners = array_fill_keys($acceptedVenueOwnerUserIds, true);

        return array_values(array_filter($orderedVenueIds, function (int $venueId) use ($venuesById, $acceptedOwners) {
            $venue = $venuesById->get($venueId);
            if (! $venue instanceof Venue) {
                return false;
            }
            $uid = (int) ($venue->user_id ?? 0);

            return $uid > 0 && isset($acceptedOwners[$uid]);
        }));
    }

    /**
     * Load only columns needed for {@see InvitedUserPayload} (no Stripe / payment tokens).
     *
     * @param  int[]  $userIds
     * @return Collection<int, User>
     */
    private function loadUsersForInvites(array $userIds): Collection
    {
        $wanted = [
            'id', 'name', 'email', 'role', 'is_active', 'email_verified_at',
            'profile_type', 'account_type', 'status', 'billing_type',
            'full_name', 'company_name', 'vat_number', 'vat_validated',
            'country', 'address', 'postal_code', 'city',
            'premium_started_at', 'profile_image_path',
        ];

        $columns = array_values(array_filter($wanted, fn (string $c) => Schema::hasColumn('users', $c)));
        if (! in_array('id', $columns, true)) {
            $columns[] = 'id';
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->select($columns)
            ->get()
            ->keyBy('id');
    }

    /**
     * First {@see TalentV2} per {@code user_id} (lowest id wins when duplicates exist).
     *
     * @param  int[]  $userIds
     * @return Collection<int|string, TalentV2>
     */
    private function loadTalentProfilesByUserId(array $userIds): Collection
    {
        if ($userIds === [] || ! Schema::hasTable('talents_v2')) {
            return collect();
        }

        return TalentV2::query()
            ->whereIn('user_id', $userIds)
            ->publishStatusPublished()
            ->orderBy('id')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $group) => $group->first());
    }

    /**
     * First {@see OrganiserV2} per {@code user_id} (lowest id wins when duplicates exist).
     *
     * @param  int[]  $userIds
     * @return Collection<int|string, OrganiserV2>
     */
    private function loadOrganiserProfilesByUserId(array $userIds): Collection
    {
        if ($userIds === [] || ! Schema::hasTable('organiser_v2')) {
            return collect();
        }

        return OrganiserV2::query()
            ->whereIn('user_id', $userIds)
            ->publishStatusPublished()
            ->orderBy('id')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $group) => $group->first());
    }

    /**
     * First {@see VenueV2} per venue owner {@code user_id} (matches legacy invited {@see Venue} via owner).
     *
     * @param  int[]  $userIds
     * @return Collection<int|string, VenueV2>
     */
    private function loadVenueV2FirstByUserId(array $userIds): Collection
    {
        if ($userIds === [] || ! Schema::hasTable('venue_v2')) {
            return collect();
        }

        return VenueV2::query()
            ->whereIn('user_id', $userIds)
            ->publishStatusPublished()
            ->with(['user', 'venueCategory', 'venueSubcategories'])
            ->orderBy('id')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $group) => $group->first());
    }

    /**
     * @param  array<int|string|null>  $raw
     * @return int[]
     */
    private function normalizeIdList(array $raw): array
    {
        $out = [];
        foreach ($raw as $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $id = (int) $v;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int|string, TalentV2>  $talentsByUserId
     * @return array<int, array<string, mixed>>
     */
    private function mapOrderedTalentInvites(Collection $usersById, Collection $talentsByUserId, array $orderedIds): array
    {
        $out = [];
        foreach ($orderedIds as $id) {
            if (! $usersById->has($id)) {
                continue;
            }
            $user = $usersById->get($id);
            $row = InvitedUserPayload::toArray($user);
            $profile = $talentsByUserId->get($id) ?? $talentsByUserId->get((string) $id);
            $row['contact_box_message'] = $profile?->contact_box_message;
            $row['contact_box_design_message'] = $profile?->contact_box_design_message;
            $row['show_contact_box'] = (bool) ($profile?->show_contact_box ?? false);
            $row['talent_v2'] = null;
            if ($profile instanceof TalentV2) {
                $row['profile_image'] = V2ProfileCoverImage::coverImageUrl($profile, $user);
                $profile->loadMissing(['category', 'talentCategory', 'talentSubcategories', 'user']);
                $row['talent_v2'] = (new TalentResource($profile))->toArray(request());
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  Collection<int|string, OrganiserV2>  $organisersByUserId
     * @return array<int, array<string, mixed>>
     */
    private function mapOrderedOrganiserInvites(Collection $usersById, Collection $organisersByUserId, array $orderedIds): array
    {
        $out = [];
        foreach ($orderedIds as $id) {
            if (! $usersById->has($id)) {
                continue;
            }
            $user = $usersById->get($id);
            $row = InvitedUserPayload::toArray($user);
            $profile = $organisersByUserId->get($id) ?? $organisersByUserId->get((string) $id);
            $row['contact_box_message'] = $profile?->contact_box_message;
            $row['contact_box_design_message'] = $profile?->contact_box_design_message;
            $row['show_contact_box'] = (bool) ($profile?->show_contact_box ?? false);
            $row['organiser_v2'] = null;
            if ($profile instanceof OrganiserV2) {
                $row['profile_image'] = V2ProfileCoverImage::coverImageUrl($profile, $user);
                $profile->loadMissing(['category', 'organiserCategory', 'organiserSubcategories', 'user']);
                $row['organiser_v2'] = (new OrganiserResource($profile))->toArray(request());
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  Collection<int, Venue>  $venuesById
     * @param  Collection<int|string, VenueV2>  $venueV2ByUserId
     * @return array<int, array<string, mixed>>
     */
    private function mapOrderedVenues(Collection $venuesById, Collection $venueV2ByUserId, array $orderedIds): array
    {
        $out = [];
        foreach ($orderedIds as $id) {
            if (! $venuesById->has($id)) {
                continue;
            }
            /** @var Venue $venue */
            $venue = $venuesById->get($id);
            $row = InvitedVenuePayload::toArray($venue);
            $row['venue_v2'] = null;
            $uid = $venue->user_id;
            if ($uid !== null) {
                $v2 = $venueV2ByUserId->get((int) $uid) ?? $venueV2ByUserId->get((string) (int) $uid);
                if ($v2 instanceof VenueV2) {
                    $v2->loadMissing(['category', 'user']);
                    $row['show_contact_box'] = (bool) ($v2->show_contact_box ?? false);
                    $row['venue_v2'] = (new VenueResource($v2))->toArray(request());
                }
            }
            $out[] = $row;
        }

        return $out;
    }
}
