<?php

namespace Tests\Feature\Controllers\Admin;

use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizerEventControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['admin']);
    }

    /** @test */
    public function it_creates_event_in_events_organizer_table()
    {
        $payload = [
            'title' => 'Test Organizer Event',
            'description' => 'Test description',
            'category_id' => 1,
            'city' => 'New York',
            'start_datetime' => now()->addDay()->toISOString(),
            'end_datetime' => now()->addDay()->addHours(4)->toISOString(),
            'organizer_name' => 'Test Organizer',
            'organizer_id' => 1,
        ];

        $response = $this->postJson('/api/admin/organizer/events', $payload);

        $response->assertStatus(201)
                ->assertJson(['success' => true]);

        // Verify the event is in events_organizer table
        $this->assertDatabaseHas('events_organizer', [
            'title' => 'Test Organizer Event',
            'city' => 'New York',
        ]);

        // Verify the event is NOT in events table
        $this->assertDatabaseMissing('events', [
            'title' => 'Test Organizer Event',
        ]);

        // Get the created event ID
        $eventId = $response->json('data.id');
        
        // Verify through model
        $event = EventOrganizer::find($eventId);
        $this->assertNotNull($event);
        $this->assertEquals('Test Organizer Event', $event->title);
    }

    /** @test */
    public function it_lists_events_from_events_organizer_table()
    {
        // Create test events in events_organizer table
        EventOrganizer::factory()->count(3)->create([
            'title' => 'Organizer Event'
        ]);

        $response = $this->getJson('/api/admin/organizer/events');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Verify all events are from events_organizer table
        $data = $response->json('data');
        $this->assertCount(3, $data);
        foreach ($data as $event) {
            $this->assertEquals('Organizer Event', substr($event['title'], 0, 15));
        }
    }

    /** @test */
    public function it_updates_event_in_events_organizer_table()
    {
        // Create test event
        $event = EventOrganizer::factory()->create([
            'title' => 'Original Title'
        ]);

        $payload = [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'category_id' => 1,
            'city' => 'Los Angeles',
            'start_datetime' => now()->addDay()->toISOString(),
            'end_datetime' => now()->addDay()->addHours(4)->toISOString(),
        ];

        $response = $this->putJson("/api/admin/organizer/events/{$event->id}", $payload);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Verify update in events_organizer table
        $this->assertDatabaseHas('events_organizer', [
            'id' => $event->id,
            'title' => 'Updated Title',
            'city' => 'Los Angeles',
        ]);

        // Verify NOT in events table
        $this->assertDatabaseMissing('events', [
            'title' => 'Updated Title',
        ]);
    }
}
