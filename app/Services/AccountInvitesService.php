<?php

namespace App\Services;

use App\Helpers\MediaHelper;
use App\Models\EventV2;
use App\Models\OrganiserV2;
use App\Models\TalentV2;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccountInvitesService
{
    /**
     * @return array{summary: array<string, int>, invites: Collection<int, array<string, mixed>>}
     */
    public function indexForOwner(int $userId, ?string $type, ?int $eventId, ?string $search): array
    {
        $events = $this->loadOwnedEventsWithInvites($userId);

        $talentProfiles = $this->loadTalentProfilesByUserId(
            $this->collectUserIdsByType($events, 'talent')
        );
        $organiserProfiles = $this->loadOrganiserProfilesByUserId(
            $this->collectUserIdsByType($events, 'organiser')
        );

        $invites = $this->flattenInvites($events, $talentProfiles, $organiserProfiles);

        $invites = $this->applyFilters($invites, $type, $eventId, $search);

        $summary = $this->buildSummary($invites);

        return [
            'summary' => $summary,
            'invites' => $invites,
        ];
    }

    public function removeInvite(int $ownerUserId, int $eventId, string $type, int $profileId): void
    {
        DB::transaction(function () use ($ownerUserId, $eventId, $type, $profileId): void {
            $event = EventV2::query()
                ->where('user_id', $ownerUserId)
                ->whereKey($eventId)
                ->firstOrFail();

            $column = match ($type) {
                'talent' => 'invited_talents',
                'organiser' => 'invited_organisers',
                'venue' => 'invited_venues',
                default => throw new \InvalidArgumentException('Invalid invite type.'),
            };

            match ($type) {
                'talent' => $event->invitedTalents()->detach($profileId),
                'organiser' => $event->invitedOrganisers()->detach($profileId),
                'venue' => $event->invitedVenues()->detach($profileId),
                default => throw new \InvalidArgumentException('Invalid invite type.'),
            };

            $event->refresh();
            $ids = $this->normalizeIdList($event->{$column} ?? []);
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $profileId));
            $event->forceFill([$column => $ids])->saveQuietly();
        });
    }

    /**
     * @return EloquentCollection<int, EventV2>
     */
    private function loadOwnedEventsWithInvites(int $userId): EloquentCollection
    {
        $userTable = (new User)->getTable();
        $userColumns = ["{$userTable}.id", "{$userTable}.name", "{$userTable}.email", "{$userTable}.full_name"];
        if (Schema::hasColumn($userTable, 'profile_image_path')) {
            $userColumns[] = "{$userTable}.profile_image_path";
        }
        if (Schema::hasColumn($userTable, 'profile_image')) {
            $userColumns[] = "{$userTable}.profile_image";
        }

        $venueTable = (new Venue)->getTable();
        $venueColumns = ["{$venueTable}.id", "{$venueTable}.name", "{$venueTable}.email", "{$venueTable}.slug", "{$venueTable}.image_path"];

        return EventV2::query()
            ->where('user_id', $userId)
            ->select(['id', 'title', 'invited_talents', 'invited_organisers', 'invited_venues'])
            ->with([
                'invitedTalents' => static fn ($q) => $q->select($userColumns),
                'invitedOrganisers' => static fn ($q) => $q->select($userColumns),
                'invitedVenues' => static fn ($q) => $q->select($venueColumns),
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, EventV2>  $events
     * @return int[]
     */
    private function collectUserIdsByType(EloquentCollection $events, string $kind): array
    {
        $ids = [];
        foreach ($events as $event) {
            if ($kind === 'talent') {
                foreach ($event->invitedTalents as $u) {
                    $ids[$u->id] = true;
                }
                foreach ($this->normalizeIdList($event->invited_talents ?? []) as $id) {
                    $ids[$id] = true;
                }
            } else {
                foreach ($event->invitedOrganisers as $u) {
                    $ids[$u->id] = true;
                }
                foreach ($this->normalizeIdList($event->invited_organisers ?? []) as $id) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  int[]  $userIds
     * @return Collection<int, TalentV2>
     */
    private function loadTalentProfilesByUserId(array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        return TalentV2::query()
            ->whereIn('user_id', $userIds)
            ->with(['talentCategory', 'category'])
            ->select(['id', 'user_id', 'title', 'slug', 'image_path', 'talent_category_id', 'category_id'])
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');
    }

    /**
     * @param  int[]  $userIds
     * @return Collection<int, OrganiserV2>
     */
    private function loadOrganiserProfilesByUserId(array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        return OrganiserV2::query()
            ->whereIn('user_id', $userIds)
            ->with(['organiserCategory', 'category'])
            ->select(['id', 'user_id', 'title', 'slug', 'image_path', 'organiser_category_id', 'category_id'])
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');
    }

    /**
     * @param  EloquentCollection<int, EventV2>  $events
     * @param  Collection<int, TalentV2>  $talentProfiles
     * @param  Collection<int, OrganiserV2>  $organiserProfiles
     * @return Collection<int, array<string, mixed>>
     */
    private function flattenInvites(
        EloquentCollection $events,
        Collection $talentProfiles,
        Collection $organiserProfiles,
    ): Collection {
        $extraUserIds = [];
        foreach ($events as $event) {
            foreach ($this->normalizeIdList($event->invited_talents ?? []) as $id) {
                if (! $event->invitedTalents->contains('id', $id)) {
                    $extraUserIds[$id] = true;
                }
            }
            foreach ($this->normalizeIdList($event->invited_organisers ?? []) as $id) {
                if (! $event->invitedOrganisers->contains('id', $id)) {
                    $extraUserIds[$id] = true;
                }
            }
        }

        $extraUsers = $extraUserIds === []
            ? collect()
            : $this->loadUsersForInvites(array_keys($extraUserIds))->keyBy('id');

        $fallbackVenueIds = [];
        foreach ($events as $event) {
            foreach ($this->normalizeIdList($event->invited_venues ?? []) as $vid) {
                if (! $event->invitedVenues->contains('id', $vid)) {
                    $fallbackVenueIds[$vid] = true;
                }
            }
        }
        $venuesById = $fallbackVenueIds === []
            ? collect()
            : Venue::query()
                ->whereIn('id', array_keys($fallbackVenueIds))
                ->select(['id', 'name', 'email', 'slug', 'image_path'])
                ->get()
                ->keyBy('id');

        $rows = collect();

        foreach ($events as $event) {
            $talentIds = array_values(array_unique(array_merge(
                $event->invitedTalents->pluck('id')->all(),
                $this->normalizeIdList($event->invited_talents ?? []),
            )));
            sort($talentIds);
            foreach ($talentIds as $uid) {
                $user = $event->invitedTalents->firstWhere('id', $uid)
                    ?? $extraUsers->get($uid);
                if (! $user instanceof User) {
                    continue;
                }
                $profile = $talentProfiles->get($uid);
                $rows->push($this->talentRow($event, $user, $profile));
            }

            $organiserIds = array_values(array_unique(array_merge(
                $event->invitedOrganisers->pluck('id')->all(),
                $this->normalizeIdList($event->invited_organisers ?? []),
            )));
            sort($organiserIds);
            foreach ($organiserIds as $uid) {
                $user = $event->invitedOrganisers->firstWhere('id', $uid)
                    ?? $extraUsers->get($uid);
                if (! $user instanceof User) {
                    continue;
                }
                $profile = $organiserProfiles->get($uid);
                $rows->push($this->organiserRow($event, $user, $profile));
            }

            $venueIds = array_values(array_unique(array_merge(
                $event->invitedVenues->pluck('id')->all(),
                $this->normalizeIdList($event->invited_venues ?? []),
            )));
            sort($venueIds);
            foreach ($venueIds as $vid) {
                $venue = $event->invitedVenues->firstWhere('id', $vid)
                    ?? $venuesById->get($vid);
                if (! $venue instanceof Venue) {
                    continue;
                }
                $rows->push($this->venueRow($event, $venue));
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $invites
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $invites, ?string $type, ?int $eventId, ?string $search): Collection
    {
        if ($type !== null && $type !== '') {
            $invites = $invites->where('type', $type)->values();
        }

        if ($eventId !== null) {
            $invites = $invites->where('event_id', $eventId)->values();
        }

        if ($search !== null && $search !== '') {
            $needle = Str::lower($search);
            $invites = $invites
                ->filter(static function (array $row) use ($needle): bool {
                    return str_contains(Str::lower((string) $row['name']), $needle)
                        || str_contains(Str::lower((string) ($row['genre'] ?? '')), $needle)
                        || str_contains(Str::lower((string) ($row['category'] ?? '')), $needle)
                        || str_contains(Str::lower((string) ($row['event_title'] ?? '')), $needle);
                })
                ->values();
        }

        return $invites;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $invites
     * @return array<string, int>
     */
    private function buildSummary(Collection $invites): array
    {
        $eventsCount = $invites->pluck('event_id')->unique()->count();

        return [
            'events_count' => $eventsCount,
            'talents' => $invites->where('type', 'talent')->count(),
            'organisers' => $invites->where('type', 'organiser')->count(),
            'venues' => $invites->where('type', 'venue')->count(),
            'total' => $invites->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function talentRow(EventV2 $event, User $user, ?TalentV2 $profile): array
    {
        $title = $profile?->title;
        $name = $title !== null && $title !== ''
            ? $title
            : (string) (($user->full_name ?? '') !== '' ? $user->full_name : $user->name);

        return [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'type' => 'talent',
            'profile_id' => $user->id,
            'name' => $name,
            'genre' => (string) ($profile?->talentCategory?->name ?? $profile?->category?->name ?? ''),
            'category' => (string) ($profile?->category?->name ?? ''),
            'image_path' => $this->resolveImagePath($profile?->image_path ?? $this->userAvatarStoragePath($user)),
            'slug' => (string) ($profile?->slug ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organiserRow(EventV2 $event, User $user, ?OrganiserV2 $profile): array
    {
        $title = $profile?->title;
        $name = $title !== null && $title !== ''
            ? $title
            : (string) (($user->full_name ?? '') !== '' ? $user->full_name : $user->name);

        return [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'type' => 'organiser',
            'profile_id' => $user->id,
            'name' => $name,
            'genre' => (string) ($profile?->organiserCategory?->name ?? $profile?->category?->name ?? ''),
            'category' => (string) ($profile?->category?->name ?? ''),
            'image_path' => $this->resolveImagePath($profile?->image_path ?? $this->userAvatarStoragePath($user)),
            'slug' => (string) ($profile?->slug ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function venueRow(EventV2 $event, Venue $venue): array
    {
        return [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'type' => 'venue',
            'profile_id' => $venue->id,
            'name' => $venue->name,
            'genre' => '',
            'category' => '',
            'image_path' => $this->resolveImagePath($venue->image_path),
            'slug' => (string) $venue->slug,
        ];
    }

    private function resolveImagePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        return MediaHelper::resolveUrl($path) ?? '';
    }

    /**
     * Stored path or external URL for the user's avatar (matches {@see User} / {@see InvitedUserPayload}).
     */
    private function userAvatarStoragePath(User $user): ?string
    {
        if (Schema::hasColumn('users', 'profile_image_path')) {
            $v = $user->profile_image_path ?? null;
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }
        if (Schema::hasColumn('users', 'profile_image')) {
            $v = $user->profile_image ?? null;
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * @param  int[]  $userIds
     * @return EloquentCollection<int, User>
     */
    private function loadUsersForInvites(array $userIds): EloquentCollection
    {
        $columns = ['id', 'name', 'email', 'full_name'];
        if (Schema::hasColumn('users', 'profile_image_path')) {
            $columns[] = 'profile_image_path';
        }
        if (Schema::hasColumn('users', 'profile_image')) {
            $columns[] = 'profile_image';
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->select($columns)
            ->get();
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
}
