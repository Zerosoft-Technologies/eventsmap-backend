<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitedEventsApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{receiver: User, sender: User, event: EventV2, invitation: EventInvitation}
     */
    private function seedInvitationFixture(): array
    {
        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $sender->id,
            'category_id' => $category->id,
        ]);

        $invitation = EventInvitation::create([
            'event_id' => $event->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'receiver_type' => EventInvitation::TYPE_TALENT,
            'status' => EventInvitation::STATUS_PENDING,
            'invitation_token' => Str::random(64),
            'token_expires_at' => now()->addHours(48),
        ]);

        return compact('receiver', 'invitation', 'event', 'sender');
    }

    public function test_authenticated_user_lists_invitations_with_full_event_payload(): void
    {
        $f = $this->seedInvitationFixture();
        Sanctum::actingAs($f['receiver']);

        $response = $this->getJson('/api/v2/invitations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.invitations.0.invitation_id', $f['invitation']->id)
            ->assertJsonPath('data.invitations.0.event.id', $f['event']->id)
            ->assertJsonStructure([
                'data' => [
                    'invitations' => [
                        [
                            'invitation_id',
                            'event_id',
                            'sender_id',
                            'receiver_id',
                            'receiver_type',
                            'status',
                            'invited_at',
                            'updated_at',
                            'sender' => ['id', 'name', 'email'],
                            'event' => ['id', 'title', 'slug'],
                        ],
                    ],
                    'pagination',
                ],
            ]);
    }

    public function test_invited_events_alias_matches_invitations_route(): void
    {
        $f = $this->seedInvitationFixture();
        Sanctum::actingAs($f['receiver']);

        $this->getJson('/api/v2/invited-events')->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_receiver_can_accept_via_authenticated_respond_route(): void
    {
        $f = $this->seedInvitationFixture();
        Sanctum::actingAs($f['receiver']);

        $this->postJson('/api/v2/invitations/'.$f['invitation']->id.'/respond', [
            'status' => 'accepted',
        ])->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('event_invitations', [
            'id' => $f['invitation']->id,
            'status' => 'accepted',
        ]);
    }

    public function test_non_receiver_cannot_respond_via_authenticated_route(): void
    {
        $f = $this->seedInvitationFixture();
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->postJson('/api/v2/invitations/'.$f['invitation']->id.'/respond', [
            'status' => 'accepted',
        ])->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_status_filter_pending(): void
    {
        $f = $this->seedInvitationFixture();
        Sanctum::actingAs($f['receiver']);

        $this->getJson('/api/v2/invitations?status=pending')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $f['invitation']->update([
            'status' => EventInvitation::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        $this->getJson('/api/v2/invitations?status=pending')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_event_timing_upcoming_filter(): void
    {
        $f = $this->seedInvitationFixture();
        Sanctum::actingAs($f['receiver']);

        $this->getJson('/api/v2/invitations?event_timing=upcoming')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $f['event']->update([
            'start_datetime' => now()->subDays(10),
            'end_datetime' => now()->subDays(9),
        ]);

        $this->getJson('/api/v2/invitations?event_timing=upcoming')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        $this->getJson('/api/v2/invitations?event_timing=past')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }
}
