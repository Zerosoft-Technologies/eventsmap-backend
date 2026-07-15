<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EventV2;
use App\Models\OrganiserV2;
use App\Models\RecurringSeries;
use App\Models\User;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringPublicOccurrenceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_approval_publishes_all_generated_occurrences(): void
    {
        [$admin, $series, $occurrences] = $this->seedSeriesWithOccurrences(4);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/recurring-series/{$series->id}/approve")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('instances_approved', 4)
            ->assertJsonPath('instances_published', 4);

        foreach ($occurrences as $occurrence) {
            $fresh = $occurrence->fresh();
            $this->assertTrue($fresh->is_approved);
            $this->assertSame(PublishStatus::PUBLISHED, $fresh->publish_status);
            $this->assertSame(EventV2::STATUS_UPCOMING, $fresh->status);
        }
    }

    public function test_public_listing_and_search_return_all_occurrences_individually(): void
    {
        [, $series, $occurrences, $standalone] = $this->seedApprovedPublicSeriesWithStandalone(4);

        $listing = $this->getJson('/api/v2/events')
            ->assertOk()
            ->json('data');

        $events = $listing['events'] ?? [];
        $this->assertCount(5, $events);
        $this->assertSame(
            collect($occurrences)->pluck('id')->sort()->values()->all(),
            collect($events)->where('series_id', $series->id)->pluck('id')->sort()->values()->all()
        );
        $this->assertCount(4, collect($events)->where('series_id', $series->id));
        $this->assertTrue(collect($events)->contains(fn ($event) => (int) $event['id'] === $standalone->id));

        $searched = $this->getJson('/api/v2/events?search=Weekly+Jam')
            ->assertOk()
            ->json('data.events');

        $this->assertCount(4, $searched);
        $this->assertSame(
            collect($occurrences)->pluck('id')->sort()->values()->all(),
            collect($searched)->pluck('id')->sort()->values()->all()
        );
    }

    public function test_public_map_returns_all_occurrence_markers(): void
    {
        [, $series, $occurrences, $standalone] = $this->seedApprovedPublicSeriesWithStandalone(4);

        $markers = $this->getJson('/api/v2/public/events/map?min_lat=52.3600&max_lat=52.3800&min_lng=4.8900&max_lng=4.9200&limit=20')
            ->assertOk()
            ->json('data.markers');

        $this->assertCount(5, $markers);
        $this->assertCount(4, collect($markers)->where('series_id', $series->id));
        $this->assertSame(
            collect($occurrences)->pluck('id')->sort()->values()->all(),
            collect($markers)->where('series_id', $series->id)->pluck('id')->sort()->values()->all()
        );
        $this->assertTrue(collect($markers)->contains(fn ($marker) => (int) $marker['id'] === $standalone->id));
    }

    public function test_public_organiser_profile_returns_all_occurrences_individually(): void
    {
        [, $series, $occurrences, $standalone, $organiser] = $this->seedApprovedPublicSeriesWithStandalone(4);

        $events = $this->getJson("/api/v2/public/organisers/{$organiser->id}")
            ->assertOk()
            ->json('data.upcoming_events');

        $this->assertCount(5, $events);
        $this->assertCount(4, collect($events)->where('series_id', $series->id));
        $this->assertSame(
            collect($occurrences)->pluck('id')->sort()->values()->all(),
            collect($events)->where('series_id', $series->id)->pluck('id')->sort()->values()->all()
        );
        $this->assertTrue(collect($events)->contains(fn ($event) => (int) $event['id'] === $standalone->id));
    }

    /**
     * @return array{0: User, 1: RecurringSeries, 2: array<int, EventV2>}
     */
    private function seedSeriesWithOccurrences(int $count): array
    {
        Carbon::setTestNow('2026-07-07 12:00:00');

        $admin = User::factory()->admin()->create(['is_active' => true]);
        $owner = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'status' => User::STATUS_ACTIVE,
        ]);
        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);

        $series = RecurringSeries::create([
            'organizer_id' => $owner->id,
            'recurrence_type' => 'weekly',
            'recurrence_rules' => [
                'weekdays' => [3],
                'event_template' => [
                    'title' => 'Weekly Jam',
                    'event_type' => 'premium',
                    'category_id' => $category->id,
                    'address' => 'Dam Square, Amsterdam',
                    'latitude' => 52.3676,
                    'longitude' => 4.9041,
                    'start_time' => '18:00:00',
                    'end_time' => '21:00:00',
                    'description' => 'Recurring weekly event',
                ],
            ],
            'timezone' => 'UTC',
            'start_date' => '2026-07-08',
            'end_date' => '2026-08-31',
            'created_by' => $owner->id,
            'is_approved' => false,
        ]);

        $occurrences = [];
        for ($i = 0; $i < $count; $i++) {
            $date = Carbon::parse('2026-07-08')->addWeeks($i)->format('Y-m-d');
            $occurrences[] = EventV2::factory()->upcoming()->create([
                'user_id' => $owner->id,
                'category_id' => $category->id,
                'title' => 'Weekly Jam',
                'series_id' => $series->id,
                'is_approved' => false,
                'publish_status' => PublishStatus::DRAFT,
                'start_date' => $date,
                'end_date' => $date,
                'event_date' => $date,
                'start_time' => '18:00:00',
                'end_time' => '21:00:00',
                'start_datetime' => "{$date} 18:00:00",
                'end_datetime' => "{$date} 21:00:00",
                'latitude' => 52.3676,
                'longitude' => 4.9041,
                'address' => 'Dam Square, Amsterdam',
            ]);
        }

        return [$admin, $series, $occurrences];
    }

    /**
     * @return array{0: User, 1: RecurringSeries, 2: array<int, EventV2>, 3: EventV2, 4: OrganiserV2}
     */
    private function seedApprovedPublicSeriesWithStandalone(int $count): array
    {
        [$admin, $series, $occurrences] = $this->seedSeriesWithOccurrences($count);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/recurring-series/{$series->id}/approve")->assertOk();
        Sanctum::actingAs(User::factory()->create()); // reset authenticated state to unrelated user

        $owner = $occurrences[0]->user;
        $category = $occurrences[0]->category;

        $standalone = EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Standalone Jam',
            'series_id' => null,
            'is_approved' => true,
            'publish_status' => PublishStatus::PUBLISHED,
            'start_date' => '2026-08-12',
            'end_date' => '2026-08-12',
            'event_date' => '2026-08-12',
            'start_time' => '19:00:00',
            'end_time' => '22:00:00',
            'start_datetime' => '2026-08-12 19:00:00',
            'end_datetime' => '2026-08-12 22:00:00',
            'latitude' => 52.3676,
            'longitude' => 4.9041,
            'address' => 'Dam Square, Amsterdam',
        ]);

        $organiser = OrganiserV2::factory()->create([
            'user_id' => $owner->id,
            'status' => ProfilePublicationStatus::UPCOMING,
            'publish_status' => PublishStatus::PUBLISHED,
            'is_approved' => true,
            'show_upcoming_events' => true,
            'show_past_events' => false,
        ]);

        return [$admin, $series, $occurrences, $standalone, $organiser];
    }
}
