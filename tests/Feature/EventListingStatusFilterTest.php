<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EventV2;
use App\Support\PublishStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventListingStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_listing_defaults_to_live_and_upcoming_only(): void
    {
        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);

        $live = EventV2::factory()->live()->create([
            'category_id' => $category->id,
            'publish_status' => PublishStatus::PUBLISHED,
        ]);
        $upcoming = EventV2::factory()->upcoming()->create([
            'category_id' => $category->id,
            'publish_status' => PublishStatus::PUBLISHED,
        ]);
        $completed = EventV2::factory()->create([
            'category_id' => $category->id,
            'status' => EventV2::STATUS_COMPLETED,
            'publish_status' => PublishStatus::PUBLISHED,
        ]);

        $response = $this->getJson('/api/v2/events?per_page=20');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data.events'))->pluck('id')->all();

        $this->assertContains($live->id, $ids);
        $this->assertContains($upcoming->id, $ids);
        $this->assertNotContains($completed->id, $ids);
    }
}

