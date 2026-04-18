<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\EventV2;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds a small set of Events V2 rows with category-consistent subcategories.
 *
 * Subcategories are stored as JSON ({@see EventV2::$subcategory_ids}) and kept in sync
 * with the {@see EventV2::subcategories()} pivot for consumers that use either path.
 */
class EventV2Seeder extends Seeder
{
    /**
     * Curated subcategory *names* per category_id — must exist in {@see SubcategoriesSeeder}.
     * Used so Music → Alternative / Ambient / Blues, Film → Documentary / Indie / Horror, etc.
     *
     * @var array<int, list<string>>
     */
    private const SUBCATEGORY_NAME_POOLS = [
        1 => ['Alternative', 'Ambient', 'Blues', 'Jazz', 'Indie', 'House', 'Techno', 'Metal', 'Pop'],
        2 => ['Salsa', 'Bachata', 'Contemporary', 'Hip Hop', 'Swing', 'Ballet', 'Kizomba'],
        3 => ['Musical', 'Comedy', 'Drama', 'Cabaret', 'Stand Up Comedy', 'Opera'],
        4 => ['Festival', 'Food & Drink', 'Parade', 'Carnival', 'Outdoor cinema', 'Fair'],
        5 => ['Documentary', 'Horror', 'Comedy', 'Drama', 'Thriller', 'Animation', 'Romance', 'Mystery'],
        6 => ['Nightclub', 'DJ/VJ Session', 'Live Music', 'Club night', 'Sunset party', 'Dancing', 'Karaoke'],
        7 => ['Musician', 'Singer', 'Dancer', 'DJ/VJ', 'Actor', 'Comic'],
        8 => ['Concert Hall', 'Nightclub', 'Theatre', 'Open-air Venue', 'Restaurant', 'Bar'],
        9 => ['Festival Organiser', 'Wedding Planner', 'Corporate Event Organiser', 'Conference Organiser', 'Party Organiser'],
    ];

    /** @var array<int, list<string>> */
    private const NL_CITIES = [
        ['name' => 'Amsterdam', 'lat' => '52.3676', 'lng' => '4.9041'],
        ['name' => 'Rotterdam', 'lat' => '51.9244', 'lng' => '4.4777'],
        ['name' => 'The Hague', 'lat' => '52.0799', 'lng' => '4.3113'],
        ['name' => 'Utrecht', 'lat' => '52.0907', 'lng' => '5.1214'],
        ['name' => 'Eindhoven', 'lat' => '51.4416', 'lng' => '5.4697'],
        ['name' => 'Groningen', 'lat' => '53.2194', 'lng' => '6.5665'],
        ['name' => 'Maastricht', 'lat' => '50.8514', 'lng' => '5.6910'],
        ['name' => 'Leiden', 'lat' => '52.1601', 'lng' => '4.4970'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $categories = Category::query()->orderBy('id')->pluck('id');
            if ($categories->isEmpty()) {
                $this->command?->warn('EventV2Seeder: no categories — skip.');

                return;
            }

            $venueIds = Venue::query()->pluck('id')->all();

            $eventHostIds = User::query()
                ->where('profile_type', User::PROFILE_EVENT)
                ->pluck('id')
                ->all();
            $fallbackUserIds = User::query()->pluck('id')->all();
            $ownerPool = ! empty($eventHostIds) ? $eventHostIds : $fallbackUserIds;

            if (empty($ownerPool)) {
                $this->command?->warn('EventV2Seeder: no users — skip.');

                return;
            }

            $talentUsers = User::query()->where('profile_type', User::PROFILE_TALENT)->pluck('id')->all();
            $organiserUsers = User::query()->whereIn('profile_type', [User::PROFILE_ORGANIZER, 'organiser'])->pluck('id')->all();

            for ($i = 1; $i <= 5; $i++) {
                $this->createPremiumEvent(
                    $ownerPool,
                    $categories->all(),
                    $venueIds,
                    $talentUsers,
                    $organiserUsers,
                    $i
                );
            }

            for ($i = 1; $i <= 3; $i++) {
                $this->createFreeEvent($ownerPool, $categories->all(), $i);
            }
        });

        $this->command?->info('EventV2Seeder: seeded premium + free events with subcategory_ids + pivot.');
    }

    /**
     * @param  list<int>  $categories
     * @param  list<int>  $venues
     * @param  list<int>  $talentUsers
     * @param  list<int>  $organiserUsers
     */
    private function createPremiumEvent(
        array $users,
        array $categories,
        array $venues,
        array $talentUsers,
        array $organiserUsers,
        int $index
    ): void {
        $userId = $users[array_rand($users)];
        $categoryId = $categories[array_rand($categories)];
        $city = self::NL_CITIES[($index - 1) % count(self::NL_CITIES)];
        $title = $this->realisticTitle($categoryId, $index, premium: true);
        $slug = Str::slug($title).'-'.Str::lower(Str::random(6));

        $subcategories = $this->pickSubcategoriesForCategory($categoryId, rand(2, min(4, EventV2::PREMIUM_MAX_SUBCATEGORIES)));
        $subIds = $subcategories->pluck('id')->map(fn (int|string $id) => (int) $id)->values()->all();

        $event = EventV2::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'slug' => $slug,
            'event_type' => 'premium',
            'category_id' => $categoryId,
            'subcategory_ids' => $subIds,
            'event_date' => now()->addDays(rand(5, 120)),
            'start_time' => sprintf('%02d:%02d:00', rand(17, 21), rand(0, 59)),
            'end_time' => sprintf('%02d:%02d:00', rand(22, 23), rand(0, 59)),
            'address' => $this->streetAddress($index).', '.$city['name'],
            'latitude' => $city['lat'],
            'longitude' => $city['lng'],
            'dress_code' => ['casual', 'smart_casual', 'formal'][array_rand(['casual', 'smart_casual', 'formal'])],
            'age_limit' => '18+',
            'entrance_status' => 'paid',
            'entrance_fee' => rand(20, 150),
            'contact_phone' => '+31 6 '.str_pad((string) rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'contact_email' => "tickets-{$slug}@example.com",
            'contact_website' => 'https://example.com/events/'.$slug,
            'description' => $this->longDescription($title, $city['name'], premium: true),
            'venue_details' => 'Main hall, accessible entrances, and staffed box office.',
            'facebook_url' => 'https://facebook.com/example',
            'instagram_url' => 'https://instagram.com/example',
            'ticket_url' => 'https://tickets.example.com/'.$slug,
            'additional_images' => [
                'events/demo/sample1.jpg',
                'events/demo/sample2.jpg',
            ],
            'venue_id' => ! empty($venues) ? $venues[array_rand($venues)] : null,
            'status' => EventV2::STATUS_UPCOMING,
            'is_free_package' => false,
            'image_path' => 'events/demo/main.jpg',
            'is_approved' => true,
            'approved_at' => now()->subDays(rand(1, 30)),
        ]);

        $this->syncSubcategoryRelations($event, $subIds);

        if (! empty($talentUsers)) {
            $event->invitedTalents()->sync(array_values(array_slice($talentUsers, 0, min(2, count($talentUsers)))));
        }

        if (! empty($organiserUsers)) {
            $event->invitedOrganisers()->sync(array_values(array_slice($organiserUsers, 0, min(2, count($organiserUsers)))));
        }

        if (! empty($venues)) {
            $event->invitedVenues()->sync(array_values(array_slice($venues, 0, min(2, count($venues)))));
        }
    }

    /**
     * @param  list<int>  $categories
     */
    private function createFreeEvent(array $users, array $categories, int $index): void
    {
        $userId = $users[array_rand($users)];
        $categoryId = $categories[array_rand($categories)];
        $city = self::NL_CITIES[($index + 3) % count(self::NL_CITIES)];
        $title = $this->realisticTitle($categoryId, $index, premium: false);
        $slug = Str::slug($title).'-'.Str::lower(Str::random(6));

        $subcategories = $this->pickSubcategoriesForCategory($categoryId, min(1, EventV2::FREE_MAX_SUBCATEGORIES));
        $subIds = $subcategories->pluck('id')->map(fn (int|string $id) => (int) $id)->values()->all();

        $event = EventV2::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'slug' => $slug,
            'event_type' => 'free',
            'category_id' => $categoryId,
            'subcategory_ids' => $subIds,
            'event_date' => now()->addDays(rand(3, 60)),
            'start_time' => sprintf('%02d:%02d:00', rand(10, 14), rand(0, 59)),
            'end_time' => sprintf('%02d:%02d:00', rand(15, 18), rand(0, 59)),
            'address' => 'Community venue '.$index.', '.$city['name'],
            'latitude' => $city['lat'],
            'longitude' => $city['lng'],
            'dress_code' => 'casual',
            'age_limit' => 'all_ages',
            'entrance_status' => 'free',
            'contact_email' => "info-{$slug}@example.com",
            'description' => $this->longDescription($title, $city['name'], premium: false),
            'status' => EventV2::STATUS_UPCOMING,
            'is_free_package' => true,
            'image_path' => 'events/demo/free.jpg',
            'is_approved' => true,
            'approved_at' => now()->subDays(rand(1, 14)),
        ]);

        $this->syncSubcategoryRelations($event, $subIds);
    }

    /**
     * @param  list<int>  $ids
     */
    private function syncSubcategoryRelations(EventV2 $event, array $ids): void
    {
        $event->subcategories()->sync($ids);
    }

    /**
     * Pick random subcategories that belong to the given category, using curated name pools.
     */
    private function pickSubcategoriesForCategory(int $categoryId, int $count): Collection
    {
        $count = max(1, $count);
        $pool = self::SUBCATEGORY_NAME_POOLS[$categoryId] ?? [];

        $query = SubCategory::query()->where('category_id', $categoryId);

        if ($pool !== []) {
            $query->whereIn('name', $pool);
        }

        $found = $query->inRandomOrder()->limit($count)->get();

        if ($found->count() < $count) {
            $found = SubCategory::query()
                ->where('category_id', $categoryId)
                ->inRandomOrder()
                ->limit($count)
                ->get();
        }

        return $found;
    }

    private function streetAddress(int $index): string
    {
        $streets = ['Keizersgracht', 'Coolsingel', 'Lange Poten', 'Oudegracht', 'Stratumseind'];

        return $streets[$index % count($streets)].' '.(10 + $index * 3);
    }

    private function realisticTitle(int $categoryId, int $index, bool $premium): string
    {
        $suffix = $premium ? ' — Premium night' : ' — Community session';
        $i = $index % 3;

        $title = match ($categoryId) {
            1 => ['Amsterdam Jazz & Ambient Night', 'Rotterdam Indie Live', 'Utrecht House Showcase'][$i],
            2 => ['Salsa Social Evening', 'Contemporary Dance Lab', 'Bachata Under the Lights'][$i],
            3 => ['Theatre Comedy Night', 'Musical Matinee', 'Drama Premiere'][$i],
            4 => ['Neighbourhood Festival', 'Food & Music Fair', 'Summer Parade'][$i],
            5 => ['Documentary Spotlight', 'Horror Double Feature', 'Late-night Thriller'][$i],
            6 => ['Nightclub Sessions', 'DJ & Lounge Experience', 'Sunset Rooftop Party'][$i],
            7 => ['Live Talent Showcase', 'Duo Musicians Night', 'Stage & Spotlight'][$i],
            8 => ['Venue Spotlight Tour', 'Hall Acoustics Evening', 'City Stages'][$i],
            9 => ['Organised City Festival', 'Corporate Showcase', 'Planner Meet & Plan'][$i],
            default => 'Featured Event '.$index.' — '.(Category::query()->whereKey($categoryId)->value('name') ?? 'Events'),
        };

        return $title.$suffix;
    }

    private function longDescription(string $title, string $city, bool $premium): string
    {
        $line = $premium
            ? 'Premium production with curated lineup, professional sound, and reserved seating options where available.'
            : 'Friendly open session — arrive early for the best spots. Donations welcome at the door.';

        return implode("\n\n", [
            "{$title} takes place in {$city}.",
            $line,
            'Program details are subject to change; follow organiser announcements for door times and accessibility notes.',
        ]);
    }
}
