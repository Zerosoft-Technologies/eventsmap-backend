<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Services\V2\EventInstanceDispositionService;
use App\Services\V2\FirebaseNotificationService;
use App\Services\V2\RecurringSeriesLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class RecurringSeriesDispositionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_generic_delete_is_blocked_for_series_instances(): void
    {
        [$owner, $category, $series, $event] = $this->seedSeriesInstance();

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v2/events/{$event->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNull($event->fresh()->deleted_at);
    }

    public function test_standalone_event_can_still_be_deleted(): void
    {
        $category = $this->createCategory();
        $owner = User::factory()->create();

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'series_id' => null,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v2/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('events_v2', ['id' => $event->id]);
    }

    public function test_disposition_soft_deletes_future_instance_without_accepted_invitations(): void
    {
        [$owner, , , $event] = $this->seedSeriesInstance();
        $service = app(EventInstanceDispositionService::class);

        $stats = $service->disposeMany([$event], $owner, 'test_delete');

        $this->assertSame(1, $stats['deleted']);
        $this->assertSame(0, $stats['cancelled']);
        $this->assertSoftDeleted('events_v2', ['id' => $event->id]);
    }

    public function test_disposition_cancels_future_instance_with_accepted_invitation(): void
    {
        Mail::fake();

        $firebase = Mockery::mock(FirebaseNotificationService::class);
        $firebase->shouldReceive('createEventCancellationNotification')->once();
        $this->app->instance(FirebaseNotificationService::class, $firebase);

        [$owner, , , $event] = $this->seedSeriesInstance();
        $invitee = User::factory()->create(['email' => 'venue@example.com']);

        EventInvitation::create([
            'event_id' => $event->id,
            'sender_id' => $owner->id,
            'receiver_id' => $invitee->id,
            'receiver_type' => EventInvitation::TYPE_VENUE,
            'status' => EventInvitation::STATUS_ACCEPTED,
            'invitation_token' => 'token-'.uniqid(),
        ]);

        $service = app(EventInstanceDispositionService::class);
        $stats = $service->disposeMany([$event], $owner, 'test_cancel');

        $this->assertSame(0, $stats['deleted']);
        $this->assertSame(1, $stats['cancelled']);
        $this->assertSame(1, $stats['notified']);

        $event->refresh();
        $this->assertSame(EventV2::STATUS_CANCELLED, $event->status);
        $this->assertNull($event->deleted_at);
    }

    public function test_end_date_shorten_skips_modified_future_instances(): void
    {
        [$owner, , $series, $event] = $this->seedSeriesInstance();
        $event->update(['is_modified' => true]);

        $service = app(RecurringSeriesLifecycleService::class);
        $stats = $service->processEndDateShortened(
            $series,
            '2026-12-31',
            '2026-06-09',
            $owner,
        );

        $this->assertSame(0, $stats['deleted']);
        $this->assertSame(0, $stats['cancelled']);
        $this->assertSame(1, $stats['skipped_already_handled']);
        $this->assertNull($event->fresh()->deleted_at);
        $this->assertNotSame(EventV2::STATUS_CANCELLED, $event->fresh()->status);
    }

    public function test_premium_expiry_deletes_series_after_future_disposal(): void
    {
        [$owner, , $series, $event] = $this->seedSeriesInstance();

        $service = app(RecurringSeriesLifecycleService::class);
        $service->handlePremiumExpiry($owner, $owner);

        $this->assertSoftDeleted('events_v2', ['id' => $event->id]);
        $this->assertDatabaseMissing('recurring_series', ['id' => $series->id]);
    }

    public function test_my_events_includes_recurring_instance_fields(): void
    {
        [$owner, , $series, $event] = $this->seedSeriesInstance();

        Sanctum::actingAs($owner);

        $this->getJson('/api/v2/my-events')
            ->assertOk()
            ->assertJsonPath('data.0.series_id', $series->id)
            ->assertJsonPath('data.0.is_series_instance', true)
            ->assertJsonPath('data.0.is_modified', false)
            ->assertJsonPath('data.0.id', $event->id);
    }

    /**
     * @return array{0: User, 1: Category, 2: RecurringSeries, 3: EventV2}
     */
    private function seedSeriesInstance(): array
    {
        Carbon::setTestNow('2026-06-09 12:00:00');

        $category = $this->createCategory();
        $owner = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'status' => User::STATUS_ACTIVE,
        ]);
        $series = $this->createSeries($owner, $category, [3]);

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'series_id' => $series->id,
            'is_modified' => false,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-10',
            'event_date' => '2026-06-10',
            'start_time' => '18:00:00',
            'end_time' => '21:00:00',
            'start_datetime' => '2026-06-10 18:00:00',
            'end_datetime' => '2026-06-10 21:00:00',
        ]);

        return [$owner, $category, $series, $event];
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);
    }

    private function createSeries(User $owner, Category $category, array $weekdays): RecurringSeries
    {
        return RecurringSeries::create([
            'organizer_id' => $owner->id,
            'recurrence_type' => 'weekly',
            'recurrence_rules' => [
                'weekdays' => $weekdays,
                'event_template' => [
                    'title' => 'Weekly Jam',
                    'event_type' => 'premium',
                    'category_id' => $category->id,
                    'address' => '1 Main St, Amsterdam',
                    'latitude' => 52.3676,
                    'longitude' => 4.9041,
                    'start_time' => '18:00:00',
                    'end_time' => '21:00:00',
                    'description' => 'Test series',
                ],
            ],
            'timezone' => 'UTC',
            'start_date' => '2026-06-01',
            'end_date' => null,
            'created_by' => $owner->id,
            'is_approved' => true,
        ]);
    }
}
