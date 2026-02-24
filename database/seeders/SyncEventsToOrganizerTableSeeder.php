<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SyncEventsToOrganizerTableSeeder extends Seeder
{
    /**
     * Sync all existing events from events table to events_organizer table.
     * This reads directly from the events table and copies to events_organizer.
     */
    public function run(): void
    {
        // Get all events from the events table
        $events = DB::table('events')->get();
        
        if ($events->isEmpty()) {
            $this->command->info('No events found in events table. Please run EventsSeeder first.');
            return;
        }

        $copiedCount = 0;
        $now = Carbon::now();

        foreach ($events as $event) {
            // Prepare data for events_organizer table
            $organizerEventData = [
                'title' => $event->title,
                'slug' => $event->slug . '-org-' . uniqid(), // Ensure unique slug
                'status' => $this->getStatusFromFlags($event),
                'description' => $event->description,
                'short_description' => $event->short_description ?? null,
                'category' => $this->getCategoryFromId($event->category_id), // Convert to string
                'price' => $event->price,
                'min_price' => $event->min_price ?? null,
                'max_price' => $event->max_price ?? null,
                'currency' => $event->currency ?? 'USD',
                'dresscode' => $event->dresscode,
                'age_restriction' => $event->age_restriction ?? null,
                'min_age' => $event->min_age,
                'max_age' => $event->max_age ?? null,
                'start_datetime' => $event->start_datetime,
                'end_datetime' => $event->end_datetime,
                'timezone' => $event->timezone ?? 'UTC',
                'is_all_day' => $event->is_all_day ?? false,
                'is_recurring' => $event->is_recurring ?? false,
                'venue_name' => $event->venue_name ?? null,
                'address' => $event->address,
                'city' => $event->city,
                'location_name' => $event->location_name ?? $event->venue_name ?? 'Default Location',
                'state' => $event->state ?? null,
                'postal_code' => $event->postal_code ?? null,
                'country' => $event->country ?? 'USA',
                'organizer_name' => $event->organizer_name ?? null,
                'organizer_id' => $event->organizer_id ?? null,
                'contact_email' => $event->contact_email ?? null,
                'contact_phone' => $event->contact_phone ?? null,
                'contact_info' => $event->contact_info ? json_decode($event->contact_info, true) : null,
                'cover_image' => $event->cover_image ?? null,
                'video_url' => $event->video_url ?? null,
                'about' => $event->about ? json_decode($event->about, true) : null,
                'location_details' => $event->location_details ? json_decode($event->location_details, true) : null,
                'booking' => $event->booking ? json_decode($event->booking, true) : null,
                'social_links' => $event->social_links ? json_decode($event->social_links, true) : null,
                'highlights' => $event->highlights ? json_decode($event->highlights, true) : [],
                'requirements' => $event->requirements ? json_decode($event->requirements, true) : [],
                'additional_info' => $event->additional_info ?? null,
                'accessibility_info' => $event->accessibility_info ?? null,
                'is_ticketed' => $event->is_ticketed ?? false,
                'is_free' => $event->is_free ?? false,
                'capacity' => $event->capacity ?? null,
                'registration_url' => $event->registration_url ?? null,
                'registration_deadline' => $event->registration_deadline ?? null,
                'meta_keywords' => $event->meta_keywords ? json_decode($event->meta_keywords, true) : [],
                'internal_notes' => $event->internal_notes ?? null,
                'custom_fields' => $event->custom_fields ? json_decode($event->custom_fields, true) : [],
                'meta_title' => $event->meta_title ?? null,
                'meta_description' => $event->meta_description ?? null,
                'tags' => $event->tags ? json_decode($event->tags, true) : [],
                'is_published' => $event->is_published,
                'is_featured' => $event->is_featured ?? false,
                'is_cancelled' => $event->is_cancelled ?? false,
                'is_archived' => $event->is_archived ?? false,
                'published_at' => $event->published_at ?? null,
                'featured_at' => $event->featured_at ?? null,
                'cancelled_at' => $event->cancelled_at ?? null,
                'archived_at' => $event->archived_at ?? null,
                'view_count' => $event->view_count ?? 0,
                'morning' => $event->morning ?? false,
                'afternoon' => $event->afternoon ?? false,
                'evening' => $event->evening ?? false,
                'night' => $event->night ?? false,
                'created_at' => $event->created_at ?? $now,
                'updated_at' => $event->updated_at ?? $now,
                'deleted_at' => $event->deleted_at ?? null,
            ];

            // Handle location data
            if (DB::getDriverName() === 'pgsql') {
                // Check if location exists in events table
                if (isset($event->location)) {
                    $organizerEventData['location'] = $event->location;
                } else {
                    // Create from latitude/longitude
                    $organizerEventData['location'] = DB::raw(
                        "ST_SetSRID(ST_MakePoint({$event->longitude}, {$event->latitude}), 4326)::geography"
                    );
                }
            } else {
                $organizerEventData['latitude'] = $event->latitude ?? null;
                $organizerEventData['longitude'] = $event->longitude ?? null;
            }

            // Insert into events_organizer table
            DB::table('events_organizer')->insert($organizerEventData);
            $copiedCount++;
        }

        $this->command->info("Successfully copied {$copiedCount} events from events table to events_organizer table.");
    }

    /**
     * Determine status from boolean flags
     */
    private function getStatusFromFlags($event): string
    {
        if ($event->is_archived) return 'archived';
        if ($event->is_cancelled) return 'cancelled';
        if ($event->is_featured) return 'featured';
        if ($event->is_published) return 'published';
        return 'draft';
    }

    /**
     * Convert category_id to category string
     */
    private function getCategoryFromId($categoryId): string
    {
        // You might want to look up the actual category name from the database
        // For now, using some common categories
        $categories = [
            1 => 'Music',
            2 => 'Sports',
            3 => 'Arts',
            4 => 'Food',
            5 => 'Technology',
            6 => 'Business',
            7 => 'Education',
            8 => 'Health',
            9 => 'Fashion',
            10 => 'Travel',
        ];

        return $categories[$categoryId] ?? 'Other';
    }
}
