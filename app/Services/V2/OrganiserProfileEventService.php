<?php

namespace App\Services\V2;

use App\Http\Resources\V2\EventResource;
use App\Models\EventV2;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OrganiserProfileEventService
{
    public function __construct(
        private readonly EventInvitedEntitiesService $eventInvitedEntitiesService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function upcomingPublicEventsPayloadForUser(int $userId): array
    {
        $events = $this->queryUpcomingPublicEventsForUser($userId)->all();
        $this->prepareEventsForPayload($events);

        return EventResource::collection($events)->resolve(request());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pastPublicEventsPayloadForUser(int $userId): array
    {
        $events = $this->queryPastPublicEventsForUser($userId)->all();
        $this->prepareEventsForPayload($events);

        return EventResource::collection($events)->resolve(request());
    }

    /**
     * @param  iterable<int, Model>  $profiles
     */
    public function hydrateUpcomingPublicEventsOnProfiles(iterable $profiles): void
    {
        $profiles = collect($profiles)->values();
        if ($profiles->isEmpty()) {
            return;
        }

        $withFlag = $profiles->filter(fn (Model $p) => (bool) ($p->getAttribute('show_upcoming_events') ?? false));
        if ($withFlag->isEmpty()) {
            return;
        }

        $userIds = $withFlag->pluck('user_id')->unique()->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($userIds === []) {
            return;
        }

        $eventsByUserId = EventV2::query()
            ->publicVisible()
            ->whereIn('status', [EventV2::STATUS_UPCOMING, EventV2::STATUS_LIVE])
            ->whereIn('user_id', $userIds)
            ->with(['category', 'venue', 'organisers', 'talents', 'user'])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get()
            ->groupBy('user_id');

        $flatUnique = $eventsByUserId->flatten(1)->unique('id')->values()->all();
        $this->prepareEventsForPayload($flatUnique);

        foreach ($withFlag as $profile) {
            $uid = (int) $profile->getAttribute('user_id');
            $profile->setAttribute('_upcoming_events', ($eventsByUserId->get($uid) ?? collect())->values()->all());
        }
    }

    /**
     * @param  iterable<int, Model>  $profiles
     */
    public function hydratePastPublicEventsOnProfiles(iterable $profiles): void
    {
        $profiles = collect($profiles)->values();
        if ($profiles->isEmpty()) {
            return;
        }

        $withFlag = $profiles->filter(fn (Model $p) => (bool) ($p->getAttribute('show_past_events') ?? false));
        if ($withFlag->isEmpty()) {
            return;
        }

        $userIds = $withFlag->pluck('user_id')->unique()->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($userIds === []) {
            return;
        }

        $eventsByUserId = EventV2::query()
            ->publicVisible()
            ->where('status', EventV2::STATUS_COMPLETED)
            ->whereIn('user_id', $userIds)
            ->with(['category', 'venue', 'organisers', 'talents', 'user'])
            ->orderByDesc('event_date')
            ->orderByDesc('start_time')
            ->get()
            ->groupBy('user_id');

        $flatUnique = $eventsByUserId->flatten(1)->unique('id')->values()->all();
        $this->prepareEventsForPayload($flatUnique);

        foreach ($withFlag as $profile) {
            $uid = (int) $profile->getAttribute('user_id');
            $profile->setAttribute('_past_events', ($eventsByUserId->get($uid) ?? collect())->values()->all());
        }
    }

    /**
     * @return Collection<int, EventV2>
     */
    private function queryUpcomingPublicEventsForUser(int $userId): Collection
    {
        return EventV2::query()
            ->publicVisible()
            ->whereIn('status', [EventV2::STATUS_UPCOMING, EventV2::STATUS_LIVE])
            ->where('user_id', $userId)
            ->with(['category', 'venue', 'organisers', 'talents', 'user'])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @return Collection<int, EventV2>
     */
    private function queryPastPublicEventsForUser(int $userId): Collection
    {
        return EventV2::query()
            ->publicVisible()
            ->where('status', EventV2::STATUS_COMPLETED)
            ->where('user_id', $userId)
            ->with(['category', 'venue', 'organisers', 'talents', 'user'])
            ->orderByDesc('event_date')
            ->orderByDesc('start_time')
            ->get();
    }

    /**
     * @param  array<int, EventV2>  $events
     */
    private function prepareEventsForPayload(array $events): void
    {
        if ($events === []) {
            return;
        }

        foreach ($events as $event) {
            if ($event instanceof EventV2) {
                $event->setRelation('subcategories', $event->subcategories_from_ids);
            }
        }

        $this->eventInvitedEntitiesService->hydrate($events);
    }
}
