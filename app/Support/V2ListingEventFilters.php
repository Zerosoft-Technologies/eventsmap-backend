<?php

namespace App\Support;

use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\OrganiserV2;
use App\Models\TalentV2;
use App\Models\VenueV2;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared date / time-of-day filters for V2 browse listings (events, venues, talents, organisers).
 */
final class V2ListingEventFilters
{
    public const SESSION_MORNING = 'morning';

    public const SESSION_AFTERNOON = 'afternoon';

    public const SESSION_EVENING = 'evening';

    public const SESSION_NIGHT = 'night';

    /** @var list<string> */
    public const SESSIONS = [
        self::SESSION_MORNING,
        self::SESSION_AFTERNOON,
        self::SESSION_EVENING,
        self::SESSION_NIGHT,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function dateValidationRules(): array
    {
        return [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionValidationRules(): array
    {
        return [
            'sessions' => 'nullable|string|max:100',
            'morning' => 'nullable',
            'afternoon' => 'nullable',
            'evening' => 'nullable',
            'night' => 'nullable',
        ];
    }

    public static function dateFrom(Request $request): ?string
    {
        $value = $request->input('date_from')
            ?? $request->input('from_date')
            ?? $request->input('start_date');

        return self::normalizeDate($value);
    }

    public static function dateTo(Request $request): ?string
    {
        $value = $request->input('date_to')
            ?? $request->input('to_date')
            ?? $request->input('end_date');

        return self::normalizeDate($value);
    }

    public static function hasDateFilter(Request $request): bool
    {
        return self::dateFrom($request) !== null || self::dateTo($request) !== null;
    }

    /**
     * Selected time-of-day slots (OR semantics). Empty when no session params or all false.
     *
     * @return list<string>
     */
    public static function selectedSessions(Request $request): array
    {
        if ($request->filled('sessions')) {
            $parts = array_map('trim', explode(',', strtolower((string) $request->input('sessions'))));

            return array_values(array_intersect($parts, self::SESSIONS));
        }

        $selected = [];
        foreach (self::SESSIONS as $session) {
            if (! $request->has($session)) {
                continue;
            }
            if (filter_var($request->input($session), FILTER_VALIDATE_BOOLEAN)) {
                $selected[] = $session;
            }
        }

        return $selected;
    }

    public static function hasSessionFilter(Request $request): bool
    {
        return self::selectedSessions($request) !== [];
    }

    /**
     * Apply date (+ optional session) constraints to an {@see EventV2} query.
     *
     * When `$upcomingOnly` is true, `event_date` is never before today (discovery / TEM listings).
     */
    public static function applyToEventQuery(
        Builder $query,
        Request $request,
        bool $includeSessions = true,
        bool $upcomingOnly = false,
    ): Builder {
        $from = self::dateFrom($request);
        $to = self::dateTo($request);

        if ($upcomingOnly) {
            $from = self::clampFromToToday($from);
        }

        if ($from !== null || $to !== null) {
            $query->dateOverlap($from, $to);
        } elseif ($upcomingOnly) {
            $query->where('event_date', '>=', now()->toDateString());
        }

        if ($includeSessions) {
            $sessions = self::selectedSessions($request);
            if ($sessions !== []) {
                $query->matchingTimeOfDaySessions($sessions);
            }
        }

        return $query;
    }

    /**
     * Restrict talents / organisers / venues to profiles linked to at least one matching published event.
     *
     * @param  Builder<TalentV2|OrganiserV2|VenueV2>  $profileQuery
     * @param  class-string<TalentV2|OrganiserV2|VenueV2>  $modelClass
     */
    public static function applyMatchingEventsToProfileQuery(
        Builder $profileQuery,
        Request $request,
        string $modelClass,
        bool $includeSessions = false,
        bool $upcomingOnly = false,
    ): Builder {
        if (! self::hasDateFilter($request) && (! $includeSessions || ! self::hasSessionFilter($request))) {
            return $profileQuery;
        }

        $profileTable = (new $modelClass)->getTable();

        return $profileQuery->where(function (Builder $profileQ) use ($request, $modelClass, $profileTable, $includeSessions, $upcomingOnly) {
            match ($modelClass) {
                TalentV2::class => self::applyTalentEventExists($profileQ, $request, $profileTable, $includeSessions, $upcomingOnly),
                OrganiserV2::class => self::applyOrganiserEventExists($profileQ, $request, $profileTable, $includeSessions, $upcomingOnly),
                VenueV2::class => self::applyVenueEventExists($profileQ, $request, $profileTable, $includeSessions, $upcomingOnly),
                default => null,
            };
        });
    }

    /**
     * @param  Builder<TalentV2|OrganiserV2|VenueV2>  $profileQ
     */
    private static function applyTalentEventExists(
        Builder $profileQ,
        Request $request,
        string $profileTable,
        bool $includeSessions,
        bool $upcomingOnly = false,
    ): void {
        $profileQ->whereExists(function ($exists) use ($request, $profileTable, $includeSessions, $upcomingOnly) {
            $exists->selectRaw('1')
                ->from('event_invitations')
                ->join('events_v2', 'events_v2.id', '=', 'event_invitations.event_id')
                ->whereColumn('event_invitations.receiver_id', "{$profileTable}.user_id")
                ->where('event_invitations.receiver_type', EventInvitation::TYPE_TALENT)
                ->where('event_invitations.status', EventInvitation::STATUS_ACCEPTED)
                ->whereNull('events_v2.deleted_at');
            self::applyPublishedBrowsableEventConstraints($exists, $request, $includeSessions, $upcomingOnly);
        });

        $profileQ->orWhereExists(function ($exists) use ($request, $profileTable, $includeSessions, $upcomingOnly) {
            $exists->selectRaw('1')
                ->from('events_v2')
                ->whereNull('events_v2.deleted_at');
            self::wrapInvitedUserJsonExists($exists, 'events_v2.invited_talents', "{$profileTable}.user_id");
            self::applyPublishedBrowsableEventConstraints($exists, $request, $includeSessions, $upcomingOnly);
        });
    }

    /**
     * @param  Builder<TalentV2|OrganiserV2|VenueV2>  $profileQ
     */
    private static function applyOrganiserEventExists(
        Builder $profileQ,
        Request $request,
        string $profileTable,
        bool $includeSessions,
        bool $upcomingOnly = false,
    ): void {
        $profileQ->whereExists(function ($exists) use ($request, $profileTable, $includeSessions, $upcomingOnly) {
            $exists->selectRaw('1')
                ->from('events_v2')
                ->whereColumn('events_v2.user_id', "{$profileTable}.user_id")
                ->whereNull('events_v2.deleted_at');
            self::applyPublishedBrowsableEventConstraints($exists, $request, $includeSessions, $upcomingOnly);
        });

        $profileQ->orWhereExists(function ($exists) use ($request, $profileTable, $includeSessions, $upcomingOnly) {
            $exists->selectRaw('1')
                ->from('event_invitations')
                ->join('events_v2', 'events_v2.id', '=', 'event_invitations.event_id')
                ->whereColumn('event_invitations.receiver_id', "{$profileTable}.user_id")
                ->where('event_invitations.receiver_type', EventInvitation::TYPE_ORGANISER)
                ->where('event_invitations.status', EventInvitation::STATUS_ACCEPTED)
                ->whereNull('events_v2.deleted_at');
            self::applyPublishedBrowsableEventConstraints($exists, $request, $includeSessions, $upcomingOnly);
        });

        $profileQ->orWhereExists(function ($exists) use ($request, $profileTable, $includeSessions, $upcomingOnly) {
            $exists->selectRaw('1')
                ->from('events_v2')
                ->whereNull('events_v2.deleted_at');
            self::wrapInvitedUserJsonExists($exists, 'events_v2.invited_organisers', "{$profileTable}.user_id");
            self::applyPublishedBrowsableEventConstraints($exists, $request, $includeSessions, $upcomingOnly);
        });
    }

    /**
     * @param  Builder<TalentV2|OrganiserV2|VenueV2>  $profileQ
     */
    private static function applyVenueEventExists(
        Builder $profileQ,
        Request $request,
        string $profileTable,
        bool $includeSessions,
        bool $upcomingOnly = false,
    ): void {
        $profileQ->whereExists(function ($exists) use ($request, $profileTable, $includeSessions, $upcomingOnly) {
            $exists->selectRaw('1')
                ->from('event_invitations')
                ->join('events_v2', 'events_v2.id', '=', 'event_invitations.event_id')
                ->whereColumn('event_invitations.receiver_id', "{$profileTable}.user_id")
                ->where('event_invitations.receiver_type', EventInvitation::TYPE_VENUE)
                ->where('event_invitations.status', EventInvitation::STATUS_ACCEPTED)
                ->whereNull('events_v2.deleted_at');
            self::applyPublishedBrowsableEventConstraints($exists, $request, $includeSessions, $upcomingOnly);
        });

        $profileQ->orWhereExists(function ($exists) use ($request, $profileTable, $includeSessions, $upcomingOnly) {
            $exists->selectRaw('1')
                ->from('events_v2')
                ->join('venues', 'venues.id', '=', 'events_v2.venue_id')
                ->whereColumn('venues.user_id', "{$profileTable}.user_id")
                ->whereNull('events_v2.deleted_at');
            self::applyPublishedBrowsableEventConstraints($exists, $request, $includeSessions, $upcomingOnly);
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function wrapInvitedUserJsonExists($query, string $jsonColumn, string $userIdColumn): void
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $query->whereRaw(
                "EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE({$jsonColumn}::jsonb, '[]'::jsonb)) AS elem WHERE elem::bigint = {$userIdColumn})"
            );

            return;
        }

        if ($driver === 'mysql') {
            $query->whereRaw(
                "JSON_CONTAINS({$jsonColumn}, CAST({$userIdColumn} AS JSON), '$')"
            );

            return;
        }

        $query->whereRaw(
            "EXISTS (SELECT 1 FROM json_each(COALESCE({$jsonColumn}, '[]')) WHERE json_each.value = {$userIdColumn})"
        );
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function applyPublishedBrowsableEventConstraints(
        $query,
        Request $request,
        bool $includeSessions,
        bool $upcomingOnly = false,
    ): void {
        $query->where('events_v2.publish_status', PublishStatus::PUBLISHED)
            ->whereNotIn('events_v2.status', [
                EventV2::STATUS_DRAFT,
                EventV2::STATUS_SUSPENDED,
                EventV2::STATUS_CANCELLED,
            ]);

        $from = self::dateFrom($request);
        $to = self::dateTo($request);
        if ($upcomingOnly) {
            $from = self::clampFromToToday($from);
        }
        if ($from !== null) {
            $query->where('events_v2.event_date', '>=', $from);
        } elseif ($upcomingOnly) {
            $query->where('events_v2.event_date', '>=', now()->toDateString());
        }
        if ($to !== null) {
            $query->where('events_v2.event_date', '<=', $to);
        }

        if ($includeSessions) {
            $sessions = self::selectedSessions($request);
            if ($sessions !== []) {
                self::applySessionConstraintsToEventSubquery($query, $sessions);
            }
        }
    }

    private static function clampFromToToday(?string $from): string
    {
        $today = now()->toDateString();

        return ($from === null || $from < $today) ? $today : $from;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  list<string>  $sessions
     */
    private static function applySessionConstraintsToEventSubquery($query, array $sessions): void
    {
        $query->where(function ($slotWrapper) use ($sessions) {
            foreach ($sessions as $session) {
                $slotWrapper->orWhere(function ($slotQ) use ($session) {
                    match ($session) {
                        self::SESSION_MORNING => $slotQ->whereTime('events_v2.start_time', '>=', '05:00:00')
                            ->whereTime('events_v2.start_time', '<', '12:00:00'),
                        self::SESSION_AFTERNOON => $slotQ->whereTime('events_v2.start_time', '>=', '12:00:00')
                            ->whereTime('events_v2.start_time', '<', '18:00:00'),
                        self::SESSION_EVENING => $slotQ->whereTime('events_v2.start_time', '>=', '18:00:00')
                            ->whereTime('events_v2.start_time', '<', '22:00:00'),
                        self::SESSION_NIGHT => $slotQ->where(function ($nightQ) {
                            $nightQ->whereTime('events_v2.start_time', '>=', '22:00:00')
                                ->orWhereTime('events_v2.start_time', '<', '05:00:00');
                        }),
                        default => null,
                    };
                });
            }
        });
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
