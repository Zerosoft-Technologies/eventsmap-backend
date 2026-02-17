<?php

namespace Database\Seeders;

use App\Models\EventOrganizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuickEventOrganizerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a simple test event
        EventOrganizer::create([
            'title' => 'Test Event for API',
            'slug' => 'test-event-for-api-' . uniqid(),
            'status' => 'published',
            'description' => 'This is a test event to verify the API returns real data',
            'short_description' => 'Test event description',
            'category' => 'Music', // String field, not category_id
            'price' => 50.00,
            'currency' => 'USD',
            'start_datetime' => now()->addDays(7),
            'end_datetime' => now()->addDays(7)->addHours(4),
            'timezone' => 'UTC',
            'city' => 'New York',
            'country' => 'USA',
            'is_published' => true,
            'is_featured' => false,
            'is_cancelled' => false,
            'is_archived' => false,
            'view_count' => 100,
        ]);

        $this->command->info('Created test event for API verification');
    }
}
