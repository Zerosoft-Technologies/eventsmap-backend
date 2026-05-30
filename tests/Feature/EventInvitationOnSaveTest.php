<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\OrganiserV2;
use App\Models\TalentV2;
use App\Models\User;
use App\Models\VenueV2;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventInvitationOnSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_event_update_creates_event_invitations_from_profile_ids(): void
    {
        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);

        $owner = User::factory()->create();
        $talentUser = User::factory()->create(['profile_type' => User::PROFILE_TALENT]);
        $organiserUser = User::factory()->create(['profile_type' => User::PROFILE_ORGANIZER]);
        $venueUser = User::factory()->create(['profile_type' => User::PROFILE_VENUE]);

        $talent = TalentV2::create([
            'user_id' => $talentUser->id,
            'title' => 'Invite Talent',
            'slug' => 'invite-talent-'.uniqid(),
            'address' => '1 Talent St',
            'status' => ProfilePublicationStatus::UPCOMING,
            'publish_status' => PublishStatus::PUBLISHED,
            'is_approved' => true,
        ]);
        $organiser = OrganiserV2::create([
            'user_id' => $organiserUser->id,
            'title' => 'Invite Organiser',
            'slug' => 'invite-organiser-'.uniqid(),
            'address' => '2 Org St',
            'status' => ProfilePublicationStatus::UPCOMING,
            'publish_status' => PublishStatus::PUBLISHED,
            'is_approved' => true,
        ]);
        $venue = VenueV2::create([
            'user_id' => $venueUser->id,
            'title' => 'Invite Venue',
            'slug' => 'invite-venue-'.uniqid(),
            'address' => '3 Venue St',
            'status' => ProfilePublicationStatus::UPCOMING,
            'publish_status' => PublishStatus::PUBLISHED,
            'is_approved' => true,
        ]);

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'event_type' => 'free',
            'is_free_package' => true,
        ]);

        Sanctum::actingAs($owner);

        $payload = [
            'title' => $event->title,
            'event_type' => 'free',
            'category_id' => $category->id,
            'start_date' => $event->start_date->format('Y-m-d'),
            'end_date' => $event->end_date?->format('Y-m-d'),
            'event_date' => $event->event_date->format('Y-m-d'),
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'start_datetime' => $event->start_datetime->toIso8601String(),
            'end_datetime' => $event->end_datetime->toIso8601String(),
            'address' => $event->address,
            'latitude' => (float) $event->latitude,
            'longitude' => (float) $event->longitude,
            'invited_talents' => [$talent->id],
            'invited_organisers' => [$organiser->id],
            'invited_venues' => [$venue->id],
        ];

        $response = $this->putJson("/api/v2/events/{$event->id}", $payload);

        $response->assertOk();

        $event->refresh();
        $this->assertSame([$talentUser->id], $event->invited_talents);
        $this->assertSame([$organiserUser->id], $event->invited_organisers);
        $this->assertSame([$venueUser->id], $event->invited_venues);

        $this->assertDatabaseHas('event_invitations', [
            'event_id' => $event->id,
            'receiver_id' => $talentUser->id,
            'receiver_type' => EventInvitation::TYPE_TALENT,
            'status' => EventInvitation::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('event_invitations', [
            'event_id' => $event->id,
            'receiver_id' => $organiserUser->id,
            'receiver_type' => EventInvitation::TYPE_ORGANISER,
        ]);
        $this->assertDatabaseHas('event_invitations', [
            'event_id' => $event->id,
            'receiver_id' => $venueUser->id,
            'receiver_type' => EventInvitation::TYPE_VENUE,
        ]);
    }
}
