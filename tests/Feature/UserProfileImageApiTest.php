<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileImageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_upload_profile_image_via_multipart_put(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['profile_image_path' => null]);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('avatar.png', 120, 120);

        $response = $this->put('/api/user/profile', [
            'profile_image' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $jsonPath = $response->json('data.user.profile_image_path');
        $this->assertIsString($jsonPath);
        $this->assertStringStartsWith('profiles/'.$user->id.'/', $jsonPath);

        $path = $user->fresh()->profile_image_path;
        $this->assertSame($jsonPath, $path);
        Storage::disk('public')->assertExists($path);

        $jsonUrl = $response->json('data.user.profile_image_url');
        $this->assertIsString($jsonUrl);
        $this->assertStringContainsString('/storage/'.$path, $jsonUrl);
    }

    public function test_avatar_field_alias_is_accepted(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['profile_image_path' => null]);
        Sanctum::actingAs($user);

        $this->put('/api/user/profile', [
            'avatar' => UploadedFile::fake()->image('pic.png', 80, 80),
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($user->fresh()->profile_image_path);
    }

    public function test_remove_profile_image_deletes_stored_file_and_clears_column(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['profile_image_path' => null]);
        Sanctum::actingAs($user);

        $this->put('/api/user/profile', [
            'profile_image' => UploadedFile::fake()->image('a.png'),
        ])->assertOk();

        $path = $user->fresh()->profile_image_path;
        Storage::disk('public')->assertExists($path);

        $this->putJson('/api/user/profile', [
            'remove_profile_image' => true,
        ])->assertOk()
            ->assertJsonPath('data.user.profile_image_path', null)
            ->assertJsonPath('data.user.profile_image_url', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->fresh()->profile_image_path);
    }

    public function test_profile_show_includes_profile_image_fields(): void
    {
        $user = User::factory()->create([
            'profile_image_path' => 'https://example.org/avatar.svg',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/user/profile')
            ->assertOk()
            ->assertJsonPath('data.user.profile_image_path', 'https://example.org/avatar.svg')
            ->assertJsonPath('data.user.profile_image_url', 'https://example.org/avatar.svg');
    }
}
