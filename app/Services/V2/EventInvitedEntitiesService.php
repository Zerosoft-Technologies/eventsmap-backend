<?php

namespace App\Services\V2;

use App\Models\EventV2;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk-loads users and venues referenced by invited_* ID arrays on events.
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

        $userIds = [];
        $venueIds = [];

        foreach ($events as $event) {
            foreach ($this->normalizeIdList($event->invited_talents ?? []) as $id) {
                $userIds[$id] = true;
            }
            foreach ($this->normalizeIdList($event->invited_organisers ?? []) as $id) {
                $userIds[$id] = true;
            }
            foreach ($this->normalizeIdList($event->invited_venues ?? []) as $id) {
                $venueIds[$id] = true;
            }
        }

        $userIds = array_keys($userIds);
        $venueIds = array_keys($venueIds);

        $usersById = $userIds === []
            ? collect()
            : $this->loadUsersForInvites($userIds);

        $venuesById = $venueIds === []
            ? collect()
            : Venue::query()->whereIn('id', $venueIds)->get()->keyBy('id');

        foreach ($events as $event) {
            $event->setAttribute(
                'invited_talents_objects',
                $this->mapOrderedUsers($usersById, $this->normalizeIdList($event->invited_talents ?? []))
            );
            $event->setAttribute(
                'invited_organisers_objects',
                $this->mapOrderedUsers($usersById, $this->normalizeIdList($event->invited_organisers ?? []))
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
            'premium_started_at', 'profile_image',
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
     * @param  Collection<int, User>  $usersById
     * @return array<int, array<string, mixed>>
     */
    private function mapOrderedUsers(Collection $usersById, array $orderedIds): array
    {
        $out = [];
        foreach ($orderedIds as $id) {
            if (! $usersById->has($id)) {
                continue;
            }
            $out[] = InvitedUserPayload::toArray($usersById->get($id));
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
