<?php

namespace Tests\Feature;

use App\Mail\GuestEventInvitationMail;
use App\Models\Category;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestEventInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function createEventForOwner(User $owner): EventV2
    {
        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);

        return EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'event_type' => 'free',
            'is_free_package' => true,
        ]);
    }

    public function test_validate_email_returns_user_exists_when_registered(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $existing = User::factory()->create(['email' => 'exists@example.com']);
        $event = $this->createEventForOwner($owner);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v2/events/{$event->id}/guest-invitations/validate-email", [
            'email' => $existing->email,
            'receiver_type' => EventInvitation::TYPE_TALENT,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'USER_EXISTS',
                'message' => 'User already exists in the system.',
            ]);
    }

    public function test_guest_invitation_is_created_and_email_sent(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $event = $this->createEventForOwner($owner);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v2/events/{$event->id}/guest-invitations", [
            'email' => 'guest@example.com',
            'name' => 'Guest User',
            'receiver_type' => EventInvitation::TYPE_VENUE,
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Invitation sent successfully.',
            ]);

        $this->assertDatabaseHas('event_invitations', [
            'event_id' => $event->id,
            'invitee_email' => 'guest@example.com',
            'receiver_type' => EventInvitation::TYPE_VENUE,
            'status' => EventInvitation::STATUS_PENDING,
            'receiver_id' => null,
        ]);

        Mail::assertSent(GuestEventInvitationMail::class, function (GuestEventInvitationMail $mail) {
            return $mail->hasTo('guest@example.com');
        });
    }

    public function test_pending_invitation_returns_invitation_pending_code(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $event = $this->createEventForOwner($owner);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v2/events/{$event->id}/guest-invitations", [
            'email' => 'pending@example.com',
            'receiver_type' => EventInvitation::TYPE_ORGANISER,
        ])->assertCreated();

        $response = $this->postJson("/api/v2/events/{$event->id}/guest-invitations/validate-email", [
            'email' => 'pending@example.com',
            'receiver_type' => EventInvitation::TYPE_ORGANISER,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'INVITATION_PENDING',
            ]);
    }

    public function test_token_lookup_for_registration(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $event = $this->createEventForOwner($owner);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v2/events/{$event->id}/guest-invitations", [
            'email' => 'newuser@example.com',
            'receiver_type' => EventInvitation::TYPE_TALENT,
        ])->assertCreated();

        $invitation = EventInvitation::where('invitee_email', 'newuser@example.com')->first();
        $this->assertNotNull($invitation?->invitation_token);

        $response = $this->getJson("/api/v2/guest-invitations/token/{$invitation->invitation_token}");

        $response->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.email', 'newuser@example.com')
            ->assertJsonPath('data.receiver_type', EventInvitation::TYPE_TALENT);
    }

    public function test_registration_accepts_guest_invitation(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $event = $this->createEventForOwner($owner);

        Sanctum::actingAs($owner);

        $this->postJson("/api/v2/events/{$event->id}/guest-invitations", [
            'email' => 'register@example.com',
            'receiver_type' => EventInvitation::TYPE_TALENT,
        ])->assertCreated();

        $invitation = EventInvitation::where('invitee_email', 'register@example.com')->first();

        $register = $this->postJson('/api/auth/register', [
            'name' => 'Registered Talent',
            'email' => 'register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'profile_type' => 'talent',
            'account_type' => 'free',
            'invitation_token' => $invitation->invitation_token,
        ]);

        $register->assertCreated();

        $invitation->refresh();
        $this->assertSame(EventInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertNotNull($invitation->receiver_id);
    }
}
