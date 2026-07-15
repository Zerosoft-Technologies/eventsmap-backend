<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EventV2;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringSeriesSourceEventTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_template_endpoint_returns_template_from_standalone_event(): void
    {
        $category = Category::create(['name' => 'Music', 'slug' => 'music-'.uniqid()]);
        $owner = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'status' => User::STATUS_ACTIVE,
        ]);

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Template Night',
            'event_type' => 'premium',
            'series_id' => null,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v2/recurring-series/event-templates/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.source_event.id', $event->id)
            ->assertJsonPath('data.event_template.title', 'Template Night')
            ->assertJsonPath('data.event_template.category_id', $category->id);
    }

    public function test_create_series_with_source_event_id_builds_template(): void
    {
        Carbon::setTestNow('2026-06-09 12:00:00');
        config(['recurring.queue_generation' => true]);

        $category = Category::create(['name' => 'Music', 'slug' => 'music-'.uniqid()]);
        $owner = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'status' => User::STATUS_ACTIVE,
        ]);

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Weekly Source',
            'event_type' => 'premium',
            'address' => '1 Main St',
            'latitude' => 52.37,
            'longitude' => 4.90,
            'start_time' => '18:00:00',
            'end_time' => '21:00:00',
            'series_id' => null,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v2/recurring-series', [
            'recurrence_type' => 'weekly',
            'recurrence_rules' => ['weekdays' => [3]],
            'timezone' => 'UTC',
            'start_date' => '2026-06-10',
            'end_date' => null,
            'source_event_id' => $event->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.source_event_id', $event->id)
            ->assertJsonPath('data.event_template.title', 'Weekly Source');

        $this->assertDatabaseHas('recurring_series', [
            'organizer_id' => $owner->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_create_rejects_end_date_beyond_one_year_horizon(): void
    {
        Carbon::setTestNow('2026-06-09 12:00:00');
        config(['recurring.queue_generation' => true]);

        $category = Category::create(['name' => 'Music', 'slug' => 'music-'.uniqid()]);
        $owner = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'status' => User::STATUS_ACTIVE,
        ]);

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'series_id' => null,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v2/recurring-series', [
            'recurrence_type' => 'weekly',
            'recurrence_rules' => ['weekdays' => [3]],
            'timezone' => 'UTC',
            'start_date' => '2026-06-10',
            'end_date' => '2028-01-01',
            'source_event_id' => $event->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }
}
