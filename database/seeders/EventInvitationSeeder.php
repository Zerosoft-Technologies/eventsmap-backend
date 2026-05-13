<?php

namespace Database\Seeders;

use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\OrganiserV2;
use App\Models\TalentV2;
use App\Models\VenueV2;
use App\Support\PublishStatus;
use Carbon\Carbon;
use Database\Seeders\Concerns\GuardsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventInvitationSeeder extends Seeder
{
    use GuardsProductionSeeding;

    public function run(): void
    {
        if ($this->shouldAbortInProduction()) {
            return;
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');

        $events = EventV2::query()
            ->where('publish_status', PublishStatus::PUBLISHED)
            ->whereIn('status', [EventV2::STATUS_UPCOMING, EventV2::STATUS_LIVE])
            ->where(function ($q) use ($now) {
                $q->whereNull('end_datetime')
                    ->orWhere('end_datetime', '>', $now);
            })
            ->inRandomOrder()
            ->limit(50)
            ->get();

        if ($events->isEmpty()) {
            $this->command?->warn('EventInvitationSeeder: no upcoming/live events; skipping invitations.');

            return;
        }

        $talentUserIds = TalentV2::query()->pluck('user_id')->unique()->filter()->map(fn ($id) => (int) $id)->values()->all();
        $organiserUserIds = OrganiserV2::query()->pluck('user_id')->unique()->filter()->map(fn ($id) => (int) $id)->values()->all();
        $venueUserIds = VenueV2::query()->pluck('user_id')->unique()->filter()->map(fn ($id) => (int) $id)->values()->all();

        if ($talentUserIds === [] && $organiserUserIds === [] && $venueUserIds === []) {
            $this->command?->warn('EventInvitationSeeder: no V2 profile owners; skipping invitations.');

            return;
        }

        $created = 0;

        DB::transaction(function () use ($events, $talentUserIds, $organiserUserIds, $venueUserIds, &$created): void {
            foreach ($events as $event) {
                $senderId = (int) $event->user_id;
                $targets = $this->pickInvitationTargets($senderId, $talentUserIds, $organiserUserIds, $venueUserIds);

                foreach ($targets as [$receiverId, $receiverType]) {
                    if (EventInvitation::query()->where('event_id', $event->id)->where('receiver_id', $receiverId)->exists()) {
                        continue;
                    }

                    $statusRoll = fake()->numberBetween(1, 100);
                    if ($statusRoll <= 55) {
                        $status = EventInvitation::STATUS_ACCEPTED;
                    } elseif ($statusRoll <= 85) {
                        $status = EventInvitation::STATUS_PENDING;
                    } else {
                        $status = EventInvitation::STATUS_REJECTED;
                    }

                    $respondedAt = in_array($status, [EventInvitation::STATUS_ACCEPTED, EventInvitation::STATUS_REJECTED], true)
                        ? Carbon::now()->subHours(fake()->numberBetween(1, 96))
                        : null;

                    EventInvitation::create([
                        'event_id' => $event->id,
                        'sender_id' => $senderId,
                        'receiver_id' => $receiverId,
                        'receiver_type' => $receiverType,
                        'status' => $status,
                        'invitation_token' => Str::random(64),
                        'token_expires_at' => Carbon::now()->addHours(EventInvitation::TOKEN_EXPIRY_HOURS),
                        'responded_at' => $respondedAt,
                    ]);
                    $created++;
                }
            }
        });

        $this->command?->info("EventInvitationSeeder: created {$created} invitation rows.");
    }

    /**
     * @param  list<int>  $talentUserIds
     * @param  list<int>  $organiserUserIds
     * @param  list<int>  $venueUserIds
     * @return list<array{0: int, 1: string}>
     */
    private function pickInvitationTargets(int $senderId, array $talentUserIds, array $organiserUserIds, array $venueUserIds): array
    {
        $used = [$senderId => true];
        $out = [];

        foreach ([
            EventInvitation::TYPE_TALENT => $talentUserIds,
            EventInvitation::TYPE_ORGANISER => $organiserUserIds,
            EventInvitation::TYPE_VENUE => $venueUserIds,
        ] as $type => $pool) {
            $candidates = array_values(array_filter(
                $pool,
                fn (int $uid) => ! isset($used[$uid])
            ));
            if ($candidates === []) {
                continue;
            }
            shuffle($candidates);
            $receiverId = (int) $candidates[0];
            $used[$receiverId] = true;
            $out[] = [$receiverId, $type];
        }

        if (fake()->boolean(28)) {
            $candidates = array_values(array_filter(
                $talentUserIds,
                fn (int $uid) => ! isset($used[$uid])
            ));
            if ($candidates !== []) {
                shuffle($candidates);
                $receiverId = (int) $candidates[0];
                $used[$receiverId] = true;
                $out[] = [$receiverId, EventInvitation::TYPE_TALENT];
            }
        }

        return $out;
    }
}
