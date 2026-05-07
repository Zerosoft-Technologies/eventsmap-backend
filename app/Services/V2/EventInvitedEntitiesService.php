<?php

namespace App\Services\V2;

use App\Models\EventV2;
use App\Models\OrganiserV2;
use App\Models\TalentV2;
use App\Models\User;
use App\Models\Venue;
use App\Support\V2ProfileCoverImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk-loads users and venues referenced by invited_* ID arrays on events.
 *
 * Invited talents / organisers resolve {@see TalentV2} / {@see OrganiserV2} by {@code user_id}
 * so contact box copy can be exposed on embedded objects.
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

        foreach ($events as $event) {
            $event->setAttribute(
                'invited_talents_objects',
                $this->mapOrderedInvitedUsersWithProfile($usersById, $talentsByUserId, $this->normalizeIdList($event->invited_talents ?? []))
            );
            $event->setAttribute(
                'invited_organisers_objects',
                $this->mapOrderedInvitedUsersWithProfile($usersById, $organisersByUserId, $this->normalizeIdList($event->invited_organisers ?? []))
            );
            $event->setAttribute(
                'invited_venues_objects',
                $this->mapOrderedVenues($venuesById, $this->normalizeIdList($event->invited_venues ?? []))
            );
        }
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
     * @param  int[]  $userIds
     * @return Collection<int, TalentV2>
     */
    private function loadTalentProfilesByUserId(array $userIds): Collection
    {
        if (! Schema::hasTable('talents_v2')) {
            return collect();
        }

        return TalentV2::query()
            ->whereIn('user_id', $userIds)
            ->select(['id', 'user_id', 'image_path', 'contact_box_message', 'contact_box_design_message'])
            ->get()
            ->keyBy('user_id');
    }

    /**
     * @param  int[]  $userIds
     * @return Collection<int, OrganiserV2>
     */
    private function loadOrganiserProfilesByUserId(array $userIds): Collection
    {
        if (! Schema::hasTable('organiser_v2')) {
            return collect();
        }

        return OrganiserV2::query()
            ->whereIn('user_id', $userIds)
            ->select(['id', 'user_id', 'image_path', 'contact_box_message', 'contact_box_design_message'])
            ->get()
            ->keyBy('user_id');
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
     * @param  Collection<int, TalentV2|OrganiserV2|null>  $profilesByUserId  keyed by {@code user_id}
     * @return array<int, array<string, mixed>>
     */
    private function mapOrderedInvitedUsersWithProfile(Collection $usersById, Collection $profilesByUserId, array $orderedIds): array
    {
        $out = [];
        foreach ($orderedIds as $id) {
            if (! $usersById->has($id)) {
                continue;
            }
            $row = InvitedUserPayload::toArray($usersById->get($id));
            $profile = $profilesByUserId->get($id);
            $row['contact_box_message'] = $profile?->contact_box_message;
            $row['contact_box_design_message'] = $profile?->contact_box_design_message;
            if ($profile instanceof TalentV2 || $profile instanceof OrganiserV2) {
                $user = $usersById->get($id);
                $row['profile_image'] = V2ProfileCoverImage::coverImageUrl($profile, $user);
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  Collection<int, Venue>  $venuesById
     * @return array<int, array<string, mixed>>
     */
    private function mapOrderedVenues(Collection $venuesById, array $orderedIds): array
    {
        $out = [];
        foreach ($orderedIds as $id) {
            if (! $venuesById->has($id)) {
                continue;
            }
            $out[] = InvitedVenuePayload::toArray($venuesById->get($id));
        }

        return $out;
    }
}
