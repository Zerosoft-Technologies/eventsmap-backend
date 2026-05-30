<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EventV2;
use App\Models\OrganiserV2;
use App\Models\TalentV2;
use App\Models\User;
use App\Models\VenueV2;
use App\Support\EventInvitationProfileIdResolver;
use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventInvitationProfileApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, talent: TalentV2, organiser: OrganiserV2, venue: VenueV2, draftTalent: TalentV2}
     */
    private function seedPickerProfiles(): array
    {
        $owner = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'country' => 'US',
        ]);

        $talentUser = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'country' => 'NL',
        ]);
        $organiserUser = User::factory()->create([
            'account_type' => User::ACCOUNT_PREMIUM,
            'country' => 'DE',
        ]);
        $venueUser = User::factory()->create([
            'account_type' => User::ACCOUNT_FREE,
            'country' => 'GB',
        ]);

        $talent = $this->createVisibleProfile(TalentV2::class, $talentUser, 'Alex Rivera');
        $organiser = $this->createVisibleProfile(OrganiserV2::class, $organiserUser, 'Sarah Chen');
        $venue = $this->createVisibleProfile(VenueV2::class, $venueUser, 'Marcus Johnson');

        $draftTalent = TalentV2::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Hidden Draft',
            'slug' => 'hidden-draft-'.uniqid(),
            'address' => '1 Draft St',
            'status' => ProfilePublicationStatus::DRAFT,
            'publish_status' => PublishStatus::DRAFT,
            'is_approved' => false,
        ]);

        return compact('owner', 'talent', 'organiser', 'venue', 'draftTalent');
    }

    /**
     * @param  class-string<TalentV2|OrganiserV2|VenueV2>  $modelClass
     */
    private function createVisibleProfile(string $modelClass, User $user, string $title): TalentV2|OrganiserV2|VenueV2
    {
        return $modelClass::create([
            'user_id' => $user->id,
            'title' => $title,
            'slug' => strtolower(str_replace(' ', '-', $title)).'-'.uniqid(),
            'address' => '123 Test St',
            'status' => ProfilePublicationStatus::UPCOMING,
            'publish_status' => PublishStatus::PUBLISHED,
            'is_approved' => true,
        ]);
    }

    public function test_authenticated_user_lists_invitation_profiles(): void
    {
        $f = $this->seedPickerProfiles();
        Sanctum::actingAs($f['owner']);

        $response = $this->getJson('/api/v2/invitation-profiles');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'profile_type', 'account_type', 'country'],
                ],
            ]);

        $data = collect($response->json('data'));
        $this->assertTrue($data->contains(fn (array $row) => $row['name'] === 'Alex Rivera' && $row['profile_type'] === 'talent'));
        $this->assertTrue($data->contains(fn (array $row) => $row['name'] === 'Sarah Chen' && $row['profile_type'] === 'organiser'));
        $this->assertTrue($data->contains(fn (array $row) => $row['name'] === 'Marcus Johnson' && $row['profile_type'] === 'venue'));
        $this->assertFalse($data->contains(fn (array $row) => $row['name'] === 'Hidden Draft'));
        $this->assertFalse($data->contains(fn (array $row) => array_key_exists('user_id', $row)));
    }

    public function test_profile_type_filter_limits_results(): void
    {
        $f = $this->seedPickerProfiles();
        Sanctum::actingAs($f['owner']);

        $response = $this->getJson('/api/v2/invitation-profiles?profile_type=talent');

        $response->assertOk();
        $types = collect($response->json('data'))->pluck('profile_type')->unique()->all();
        $this->assertSame(['talent'], $types);
    }

    public function test_event_scoped_picker_excludes_already_invited_users(): void
    {
        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);

        $f = $this->seedPickerProfiles();
        Sanctum::actingAs($f['owner']);

        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $f['owner']->id,
            'category_id' => $category->id,
            'invited_talents' => [$f['talent']->user_id],
        ]);

        $response = $this->getJson("/api/v2/events/{$event->id}/invitation-profiles?exclude_invited=1");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertFalse($names->contains('Alex Rivera'));
        $this->assertTrue($names->contains('Sarah Chen'));
    }

    public function test_non_owner_cannot_use_event_scoped_picker(): void
    {
        $category = Category::create([
            'name' => 'Music',
            'slug' => 'music-'.uniqid(),
        ]);

        $f = $this->seedPickerProfiles();
        $event = EventV2::factory()->upcoming()->create([
            'user_id' => $f['owner']->id,
            'category_id' => $category->id,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v2/events/{$event->id}/invitation-profiles")
            ->assertForbidden();
    }

    public function test_lists_published_profile_without_admin_approval(): void
    {
        $owner = User::factory()->create();
        $talentUser = User::factory()->create(['country' => 'NL']);
        TalentV2::create([
            'user_id' => $talentUser->id,
            'title' => 'Unapproved Published',
            'slug' => 'unapproved-published-'.uniqid(),
            'address' => '123 Test St',
            'status' => ProfilePublicationStatus::DRAFT,
            'publish_status' => PublishStatus::PUBLISHED,
            'is_approved' => false,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v2/invitation-profiles?profile_type=talent');

        $response->assertOk();
        $this->assertTrue(
            collect($response->json('data'))->contains(fn (array $row) => $row['name'] === 'Unapproved Published')
        );
    }

    public function test_lists_profile_with_upcoming_status_even_when_publish_is_draft(): void
    {
        $owner = User::factory()->create();
        $talentUser = User::factory()->create(['country' => 'DE']);
        TalentV2::create([
            'user_id' => $talentUser->id,
            'title' => 'Upcoming Only',
            'slug' => 'upcoming-only-'.uniqid(),
            'address' => '123 Test St',
            'status' => ProfilePublicationStatus::UPCOMING,
            'publish_status' => PublishStatus::DRAFT,
            'is_approved' => false,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v2/invitation-profiles?profile_type=talent');

        $response->assertOk();
        $this->assertTrue(
            collect($response->json('data'))->contains(fn (array $row) => $row['name'] === 'Upcoming Only')
        );
    }

    public function test_resolver_maps_profile_ids_to_user_ids(): void
    {
        $talentUser = User::factory()->create();
        $talent = $this->createVisibleProfile(TalentV2::class, $talentUser, 'Resolver Talent');

        $userIds = EventInvitationProfileIdResolver::talentIdsToUserIds([$talent->id]);

        $this->assertSame([$talentUser->id], $userIds);
    }
}
