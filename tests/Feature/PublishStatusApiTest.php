<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EventV2;
use App\Models\TalentV2;
use App\Models\User;
use App\Support\PublishStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublishStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_patch_talent_publish_status(): void
    {
        $user = User::factory()->create();
        $talent = TalentV2::create([
            'user_id' => $user->id,
            'title' => 'Test Talent',
            'slug' => 'test-talent-'.uniqid(),
            'address' => '123 Test St',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v2/talents/{$talent->id}/publish-status", [
            'publish_status' => PublishStatus::PUBLISHED,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.publish_status', PublishStatus::PUBLISHED)
            ->assertJsonPath('data.publish_status_label', 'Published');

        $this->assertSame(PublishStatus::PUBLISHED, $talent->fresh()->publish_status);
    }

    public function test_owner_can_patch_event_publish_status(): void
    {
        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);
        $user = User::factory()->create();
        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v2/events/{$event->id}/publish-status", [
            'publish_status' => PublishStatus::PUBLISHED,
        ])
            ->assertOk()
            ->assertJsonPath('data.publish_status', PublishStatus::PUBLISHED);

        $this->assertSame(PublishStatus::PUBLISHED, $event->fresh()->publish_status);
    }

    public function test_meta_publish_statuses_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v2/meta/publish-statuses')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.statuses', PublishStatus::ALL);
    }

    public function test_invalid_publish_status_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $talent = TalentV2::create([
            'user_id' => $user->id,
            'title' => 'Test',
            'slug' => 'test-'.uniqid(),
            'address' => 'A',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v2/talents/{$talent->id}/publish-status", ['publish_status' => 'live'])
            ->assertStatus(422);
    }

    public function test_non_owner_cannot_patch_publish_status(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $talent = TalentV2::create([
            'user_id' => $owner->id,
            'title' => 'Test',
            'slug' => 'test-'.uniqid(),
            'address' => 'A',
        ]);

        Sanctum::actingAs($other);

        $this->patchJson("/api/v2/talents/{$talent->id}/publish-status", [
            'publish_status' => PublishStatus::PUBLISHED,
        ])->assertStatus(403);
    }
}
