<?php

namespace Tests\Feature\Controllers\Admin;

use App\Models\EventOrganizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\User;

class OrganizerEventShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['admin']);
    }

    /** @test */
    public function it_returns_null_when_organizer_event_not_found()
    {
        $response = $this->getJson('/api/admin/organizer/99999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Organizer event not found',
                    'data' => null
                ]);
    }

    /** @test */
    public function it_returns_event_when_found()
    {
        // Create a test event
        $event = EventOrganizer::factory()->create([
            'title' => 'Test Event',
            'category' => 'Music',
        ]);

        $response = $this->getJson("/api/admin/organizer/{$event->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $event->id,
                        'title' => 'Test Event',
                        'category' => 'Music',
                    ]
                ]);
    }

    /** @test */
    public function it_returns_null_for_soft_deleted_event()
    {
        // Create and soft delete an event
        $event = EventOrganizer::factory()->create();
        $event->delete();

        $response = $this->getJson("/api/admin/organizer/{$event->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $event->id,
                        'deleted_at' => notNull(), // Should have deleted_at timestamp
                    ]
                ]);
    }
}
