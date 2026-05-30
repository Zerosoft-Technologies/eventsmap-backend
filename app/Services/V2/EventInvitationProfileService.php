<?php

namespace App\Services\V2;

use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\OrganiserV2;
use App\Models\TalentV2;
use App\Models\User;
use App\Models\VenueV2;
use App\Support\ProfilePublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EventInvitationProfileService
{
    public const TYPE_TALENT = 'talent';

    public const TYPE_ORGANISER = 'organiser';

    public const TYPE_VENUE = 'venue';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_TALENT,
        self::TYPE_ORGANISER,
        self::TYPE_VENUE,
    ];

    /**
     * @return list<array{id: int, name: string, profile_type: string, account_type: string, country: ?string, user_id: int}>
     */
    public function listForPicker(Request $request, ?EventV2 $event = null): array
    {
        $types = $this->resolvedTypes($request);
        $excludeUserIds = $this->excludedUserIds($request, $event);

        $rows = [];

        if (in_array(self::TYPE_TALENT, $types, true)) {
            $rows = array_merge($rows, $this->talentRows($request, $excludeUserIds));
        }

        if (in_array(self::TYPE_ORGANISER, $types, true)) {
            $rows = array_merge($rows, $this->organiserRows($request, $excludeUserIds));
        }

        if (in_array(self::TYPE_VENUE, $types, true)) {
            $rows = array_merge($rows, $this->venueRows($request, $excludeUserIds));
        }

        usort($rows, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function resolvedTypes(Request $request): array
    {
        if (! $request->filled('profile_type')) {
            return self::TYPES;
        }

        $raw = $request->input('profile_type');
        $parts = is_array($raw)
            ? $raw
            : explode(',', (string) $raw);

        $types = [];
        foreach ($parts as $part) {
            $normalized = strtolower(trim((string) $part));
            if ($normalized === 'organizer') {
                $normalized = self::TYPE_ORGANISER;
            }
            if (in_array($normalized, self::TYPES, true)) {
                $types[] = $normalized;
            }
        }

        return $types !== [] ? array_values(array_unique($types)) : self::TYPES;
    }

    /**
     * @return list<int>
     */
    private function excludedUserIds(Request $request, ?EventV2 $event): array
    {
        $ids = [];

        if ($request->boolean('exclude_self', false) && $request->user()) {
            $ids[$request->user()->id] = (int) $request->user()->id;
        }

        if ($event && $request->boolean('exclude_invited', false)) {
            foreach ($event->invited_talents ?? [] as $uid) {
                $ids[(int) $uid] = (int) $uid;
            }
            foreach ($event->invited_organisers ?? [] as $uid) {
                $ids[(int) $uid] = (int) $uid;
            }
            foreach ($event->invited_venues ?? [] as $uid) {
                $ids[(int) $uid] = (int) $uid;
            }

            $pending = EventInvitation::query()
                ->where('event_id', $event->id)
                ->whereIn('status', [EventInvitation::STATUS_PENDING, EventInvitation::STATUS_ACCEPTED])
                ->pluck('receiver_id');

            foreach ($pending as $uid) {
                $ids[(int) $uid] = (int) $uid;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  list<int>  $excludeUserIds
     * @return list<array{id: int, name: string, profile_type: string, account_type: string, country: ?string, user_id: int}>
     */
    private function talentRows(Request $request, array $excludeUserIds): array
    {
        $query = TalentV2::query()
            ->invitableForEvent()
            ->with(['user:id,account_type,country']);

        $this->applySearch($query, $request, 'talents_v2.title');
        $this->applyCountryFilter($query, $request, 'talents_v2.nationality');
        $this->applyAccountTypeFilter($query, $request);

        if ($excludeUserIds !== []) {
            $query->whereNotIn('talents_v2.user_id', $excludeUserIds);
        }

        return $this->mapProfiles($query->orderBy('talents_v2.title')->get(), self::TYPE_TALENT);
    }

    /**
     * @param  list<int>  $excludeUserIds
     * @return list<array{id: int, name: string, profile_type: string, account_type: string, country: ?string, user_id: int}>
     */
    private function organiserRows(Request $request, array $excludeUserIds): array
    {
        $query = OrganiserV2::query()
            ->invitableForEvent()
            ->with(['user:id,account_type,country']);

        $this->applySearch($query, $request, 'organiser_v2.title');
        $this->applyCountryFilter($query, $request);
        $this->applyAccountTypeFilter($query, $request);

        if ($excludeUserIds !== []) {
            $query->whereNotIn('organiser_v2.user_id', $excludeUserIds);
        }

        return $this->mapProfiles($query->orderBy('organiser_v2.title')->get(), self::TYPE_ORGANISER);
    }

    /**
     * @param  list<int>  $excludeUserIds
     * @return list<array{id: int, name: string, profile_type: string, account_type: string, country: ?string, user_id: int}>
     */
    private function venueRows(Request $request, array $excludeUserIds): array
    {
        $query = VenueV2::query()
            ->invitableForEvent()
            ->with(['user:id,account_type,country']);

        $this->applySearch($query, $request, 'venue_v2.title');
        $this->applyCountryFilter($query, $request);
        $this->applyAccountTypeFilter($query, $request);

        if ($excludeUserIds !== []) {
            $query->whereNotIn('venue_v2.user_id', $excludeUserIds);
        }

        return $this->mapProfiles($query->orderBy('venue_v2.title')->get(), self::TYPE_VENUE);
    }

    /**
     * @param  Builder<TalentV2|OrganiserV2|VenueV2>  $query
     */
    private function applySearch(Builder $query, Request $request, string $titleColumn): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = $request->input('search');
        $op = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $pattern = '%'.$search.'%';

        $query->where(function (Builder $sub) use ($pattern, $op, $titleColumn) {
            $sub->where($titleColumn, $op, $pattern)
                ->orWhereHas('user', function (Builder $userQ) use ($pattern, $op) {
                    $userQ->where('name', $op, $pattern)
                        ->orWhere('email', $op, $pattern);
                });
        });
    }

    /**
     * @param  Builder<TalentV2|OrganiserV2|VenueV2>  $query
     */
    private function applyCountryFilter(Builder $query, Request $request, ?string $profileCountryColumn = null): void
    {
        if (! $request->filled('country')) {
            return;
        }

        $country = strtoupper(trim((string) $request->input('country')));

        $query->where(function (Builder $sub) use ($country, $profileCountryColumn) {
            $sub->whereHas('user', fn (Builder $userQ) => $userQ->where('country', $country));

            if ($profileCountryColumn !== null) {
                $sub->orWhere($profileCountryColumn, $country);
            }
        });
    }

    /**
     * @param  Builder<TalentV2|OrganiserV2|VenueV2>  $query
     */
    private function applyAccountTypeFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('account_type')) {
            return;
        }

        $query->whereHas('user', fn (Builder $userQ) => $userQ->where('account_type', $request->input('account_type')));
    }

    /**
     * @param  Collection<int, TalentV2|OrganiserV2|VenueV2>  $profiles
     * @return list<array{id: int, name: string, profile_type: string, account_type: string, country: ?string, user_id: int}>
     */
    private function mapProfiles(Collection $profiles, string $profileType): array
    {
        $out = [];

        foreach ($profiles as $profile) {
            $user = $profile->user;
            if (! $user) {
                continue;
            }

            $out[] = [
                'id' => (int) $profile->id,
                'name' => (string) $profile->title,
                'profile_type' => $profileType,
                'account_type' => (string) ($user->account_type ?? User::ACCOUNT_FREE),
                'country' => $this->resolveCountry($user, $profile),
                'user_id' => (int) $profile->user_id,
            ];
        }

        return $out;
    }

    /**
     * @param  TalentV2|OrganiserV2|VenueV2  $profile
     */
    private function resolveCountry(User $user, $profile): ?string
    {
        $fromUser = $user->country;
        if (is_string($fromUser) && $fromUser !== '') {
            return strtoupper(strlen(trim($fromUser)) <= 3 ? trim($fromUser) : $fromUser);
        }

        if ($profile instanceof TalentV2 && is_string($profile->nationality) && $profile->nationality !== '') {
            return strtoupper(strlen(trim($profile->nationality)) <= 3 ? trim($profile->nationality) : $profile->nationality);
        }

        return null;
    }
}
