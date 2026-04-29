<?php

namespace Tests\Feature;

use App\Models\TalentV2;
use App\Models\User;
use App\Support\ProfilePublicationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePublicationStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_patch_talent_publication_status(): void
    {
        $user = User::factory()->create();
        $talent = TalentV2::create([
            'user_id' => $user->id,
            'title' => 'Test Talent',
            'slug' => 'test-talent-'.uniqid(),
            'address' => '123 Test St',
            'status' => ProfilePublicationStatus::DRAFT,
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v2/talents/{$talent->id}/status", [
            'status' => ProfilePublicationStatus::UPCOMING,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ProfilePublicationStatus::UPCOMING)
            ->assertJsonPath('data.status_label', 'Upcoming');

        $this->assertSame(
            ProfilePublicationStatus::UPCOMING,
            $talent->fresh()->status
        );
    }

    public function test_meta_endpoint_returns_labels_and_sidebar_options(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v2/meta/profile-publication-statuses')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sidebar_options', ProfilePublicationStatus::SIDEBAR_OPTIONS);
    }

    public function test_invalid_status_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $talent = TalentV2::create([
            'user_id' => $user->id,
            'title' => 'Test',
            'slug' => 'test-'.uniqid(),
            'address' => 'A',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/v2/talents/{$talent->id}/status", ['status' => 'invalid'])
            ->assertStatus(422);
    }

    public function test_non_owner_cannot_patch_status(): void
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

        $this->patchJson("/api/v2/talents/{$talent->id}/status", [
            'status' => ProfilePublicationStatus::COMPLETED,
        ])->assertStatus(403);
    }
}
