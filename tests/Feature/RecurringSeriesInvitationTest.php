<?php

namespace Tests\Feature;

use App\Jobs\CreateRecurringSeriesInvitationsJob;
use App\Models\Category;
use App\Models\EventInvitation;
use App\Models\EventV2;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Services\V2\RecurringEventGenerationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecurringSeriesInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generation_copies_invited_users_to_instances(): void
    {
        Carbon::setTestNow('2026-06-09 12:00:00');
        config([
            'recurring.queue_invitations' => false,
            'recurring.queue_generation' => false,
        ]);
        Mail::fake();

        [$owner, $category, $invitee] = $this->seedActors();
        $series = $this->createSeriesWithInvites($owner, $category, $invitee);

        $stats = app(RecurringEventGenerationService::class)->generate($series);

        $this->assertGreaterThan(0, $stats['created']);

        $instance = EventV2::query()
            ->where('series_id', $series->id)
            ->first();

        $this->assertNotNull($instance);
        $this->assertSame([(int) $invitee->id], $instance->invited_talents);

        $invitation = EventInvitation::query()
            ->where('event_id', $instance->id)
            ->where('receiver_id', $invitee->id)
            ->first();

        $this->assertNotNull($invitation);
        $this->assertSame(EventInvitation::STATUS_PENDING, $invitation->status);
    }

    public function test_generation_queues_invitation_job_when_configured(): void
    {
        Carbon::setTestNow('2026-06-09 12:00:00');
        config([
            'recurring.queue_invitations' => true,
            'recurring.queue_generation' => false,
        ]);
        Bus::fake();

        [$owner, $category, $invitee] = $this->seedActors();
        $series = $this->createSeriesWithInvites($owner, $category, $invitee);

        app(RecurringEventGenerationService::class)->generate($series);

        Bus::assertDispatched(CreateRecurringSeriesInvitationsJob::class);
    }

    public function test_source_event_template_includes_invited_users(): void
    {
        [$owner, $category, $invitee] = $this->seedActors();

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'series_id' => null,
            'invited_talents' => [(int) $invitee->id],
        ]);

        $this->actingAs($owner);

        $this->getJson("/api/v2/recurring-series/event-templates/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.event_template.invited_talents.0', $invitee->id);
    }

    /**
     * @return array{0: User, 1: Category, 2: User}
     */
    private function seedActors(): array
    {
        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);

        $owner = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'status' => User::STATUS_ACTIVE,
        ]);

        $invitee = User::factory()->create(['email' => 'talent@example.com']);

        return [$owner, $category, $invitee];
    }

    private function createSeriesWithInvites(User $owner, Category $category, User $invitee): RecurringSeries
    {
        return RecurringSeries::create([
            'organizer_id' => $owner->id,
            'recurrence_type' => 'weekly',
            'recurrence_rules' => [
                'weekdays' => [3],
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
                    'dress_code' => 'casual',
                    'age_limit' => '18+',
                    'entrance_status' => 'free',
                    'invited_talents' => [(int) $invitee->id],
                ],
            ],
            'timezone' => 'UTC',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-24',
            'created_by' => $owner->id,
            'is_approved' => true,
        ]);
    }
}
