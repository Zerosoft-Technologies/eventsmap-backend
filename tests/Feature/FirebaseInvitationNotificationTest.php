<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\TalentV2;
use App\Models\User;
use App\Services\V2\EventInvitationService;
use App\Services\V2\FirebaseNotificationService;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FirebaseInvitationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sending_invitations_pushes_firestore_notification_per_created_row(): void
    {
        $firebase = Mockery::mock(FirebaseNotificationService::class);
        $firebase->shouldReceive('createInvitationNotification')
            ->once()
            ->with(Mockery::on(fn (EventInvitation $invitation) => $invitation->receiver_id > 0));
        $this->app->instance(FirebaseNotificationService::class, $firebase);

        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);

        $owner = User::factory()->create();
        $talentUser = User::factory()->create();
        $talent = TalentV2::create([
            'user_id' => $talentUser->id,
            'title' => 'Notify Talent',
            'slug' => 'notify-talent-'.uniqid(),
            'address' => '1 St',
            'status' => ProfilePublicationStatus::UPCOMING,
            'publish_status' => PublishStatus::PUBLISHED,
            'is_approved' => true,
        ]);

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'event_type' => 'free',
            'invited_talents' => [$talentUser->id],
        ]);

        app(EventInvitationService::class)->createInvitationsForEvent($event, $owner);

        $this->assertDatabaseCount('event_invitations', 1);
    }

    public function test_responding_marks_firestore_notification_completed(): void
    {
        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $firebase = Mockery::mock(FirebaseNotificationService::class);
        $firebase->shouldReceive('markInvitationNotificationCompleted')
            ->once()
            ->withArgs(function (int $invitationId, string $receiverId) use ($receiver) {
                return $invitationId > 0 && $receiverId === (string) $receiver->id;
            });
        $this->app->instance(FirebaseNotificationService::class, $firebase);

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
            'invitation_token' => str_repeat('b', 64),
            'token_expires_at' => now()->addDay(),
        ]);

        $updated = app(EventInvitationService::class)->respond(
            $invitation,
            EventInvitation::STATUS_ACCEPTED,
            $receiver
        );

        $this->assertSame(EventInvitation::STATUS_ACCEPTED, $updated->status);
    }
}
