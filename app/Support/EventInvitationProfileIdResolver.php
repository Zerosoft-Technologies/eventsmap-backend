<?php

namespace App\Support;

use App\Models\OrganiserV2;
use App\Models\TalentV2;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueV2;

/**
 * Maps V2 profile ids from the invitation picker to user ids persisted on events / invitations.
 */
final class EventInvitationProfileIdResolver
{
    /**
     * @param  list<int|string|null>  $ids  Profile ids and/or legacy user ids
     * @return list<int> Unique user ids
     */
    public static function talentIdsToUserIds(array $ids): array
    {
        return self::resolve($ids, TalentV2::class);
    }

    /**
     * @param  list<int|string|null>  $ids
     * @return list<int>
     */
    public static function organiserIdsToUserIds(array $ids): array
    {
        return self::resolve($ids, OrganiserV2::class);
    }

    /**
     * @param  list<int|string|null>  $ids
     * @return list<int>
     */
    public static function venueIdsToUserIds(array $ids): array
    {
        return self::resolveVenueIds($ids);
    }

    /**
     * @param  list<int|string|null>  $ids
     * @param  class-string<TalentV2|OrganiserV2|VenueV2>  $profileClass
     * @return list<int>
     */
    private static function resolve(array $ids, string $profileClass): array
    {
        $userIds = [];

        foreach ($ids as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }

            $id = (int) $raw;
            if ($id <= 0) {
                continue;
            }

            $ownerUserId = $profileClass::query()->whereKey($id)->value('user_id');
            if ($ownerUserId !== null) {
                $userIds[(int) $ownerUserId] = (int) $ownerUserId;

                continue;
            }

            if (User::query()->whereKey($id)->exists()) {
                $userIds[$id] = $id;
            }
        }

        return array_values($userIds);
    }

    /**
     * @param  list<int|string|null>  $ids
     * @return list<int>
     */
    private static function resolveVenueIds(array $ids): array
    {
        $userIds = [];

        foreach ($ids as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }

            $id = (int) $raw;
            if ($id <= 0) {
                continue;
            }

            $fromV2 = VenueV2::query()->whereKey($id)->value('user_id');
            if ($fromV2 !== null) {
                $userIds[(int) $fromV2] = (int) $fromV2;

                continue;
            }

            $fromLegacy = Venue::query()->whereKey($id)->value('user_id');
            if ($fromLegacy !== null) {
                $userIds[(int) $fromLegacy] = (int) $fromLegacy;

                continue;
            }

            if (User::query()->whereKey($id)->exists()) {
                $userIds[$id] = $id;
            }
        }

        return array_values($userIds);
    }
}
