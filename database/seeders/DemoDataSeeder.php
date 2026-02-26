<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\EventV2;
use App\Models\User;
use App\Models\Venue;
use App\Models\Wishlist;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DemoDataSeeder - Creates realistic demo data for Events Map.
 * 
 * Generates:
 * - 15 users with varied roles and profiles
 * - 90 events (6 per user)
 * - Random wishlist relationships
 * - Categories and venues
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting DemoDataSeeder...');
        
        // Disable foreign key checks temporarily (PostgreSQL compatible)
        DB::statement('SET session_replication_role = replica;');
        
        // Clear existing data
        $this->command->info('🗑️  Clearing existing demo data...');
        Wishlist::query()->delete();
        EventV2::query()->delete();
        Venue::query()->delete();
        SubCategory::query()->delete();
        Category::query()->delete();
        User::query()->whereNotIn('email', ['admin@example.com', 'superadmin@example.com'])->delete();
        
        // Re-enable foreign key checks
        DB::statement('SET session_replication_role = DEFAULT;');
        
        // Step 1: Create Categories
        $this->command->info('📂 Creating categories...');
        $categories = $this->createCategories();
        
        // Step 2: Create Subcategories
        $this->command->info('📋 Creating subcategories...');
        $subcategories = $this->createSubcategories($categories);
        
        // Step 3: Create Venues
        $this->command->info('🏛️  Creating venues...');
        $venues = $this->createVenues();
        
        // Step 4: Create Users
        $this->command->info('👥 Creating 15 users...');
        $users = $this->createUsers();
        
        // Step 5: Create Events (6 per user)
        $this->command->info('🎉 Creating 90 events (6 per user)...');
        $events = $this->createEvents($users, $categories, $venues);
        
        // Step 6: Create Wishlist relationships
        $this->command->info('❤️  Creating wishlist relationships...');
        $this->createWishlists($users, $events);
        
        // Step 7: Add some event analytics
        $this->command->info('📊 Adding event analytics...');
        $this->createAnalytics($events);
        
        $this->command->info('✅ DemoDataSeeder completed successfully!');
        $this->command->info('📈 Summary:');
        $this->command->info("   - Users: {$users->count()}");
        $this->command->info("   - Events: {$events->count()}");
        $this->command->info("   - Wishlists: " . Wishlist::count());
        $this->command->info("   - Categories: {$categories->count()}");
        $this->command->info("   - Venues: {$venues->count()}");
    }
    
    /**
     * Create event categories.
     */
    private function createCategories()
    {
        $categoryData = [
            ['name' => 'Music V2', 'slug' => 'music-v2', 'description' => 'Live music events, concerts, festivals'],
            ['name' => 'Sports V2', 'slug' => 'sports-v2', 'description' => 'Sports competitions, games, fitness events'],
            ['name' => 'Arts & Culture V2', 'slug' => 'arts-culture-v2', 'description' => 'Art exhibitions, theater, cultural events'],
            ['name' => 'Food & Drink V2', 'slug' => 'food-drink-v2', 'description' => 'Food festivals, tastings, culinary events'],
            ['name' => 'Business V2', 'slug' => 'business-v2', 'description' => 'Conferences, networking, workshops'],
            ['name' => 'Technology V2', 'slug' => 'technology-v2', 'description' => 'Tech meetups, hackathons, product launches'],
            ['name' => 'Nightlife V2', 'slug' => 'nightlife-v2', 'description' => 'Club nights, parties, DJ events'],
            ['name' => 'Outdoor V2', 'slug' => 'outdoor-v2', 'description' => 'Outdoor activities, adventures, nature events'],
        ];
        
        $categories = collect();
        
        foreach ($categoryData as $data) {
            $category = Category::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                ]
            );
            $categories->push($category);
        }
        
        return $categories;
    }
    
    /**
     * Create subcategories for each category.
     */
    private function createSubcategories($categories)
    {
        $subcategoryMap = [
            'Music V2' => ['Rock', 'Electronic', 'Jazz', 'Classical', 'Hip Hop', 'Pop'],
            'Sports V2' => ['Football', 'Basketball', 'Running', 'Cycling', 'Tennis', 'Swimming'],
            'Arts & Culture V2' => ['Exhibition', 'Theater', 'Dance', 'Film', 'Literature', 'Comedy'],
            'Food & Drink V2' => ['Festival', 'Wine Tasting', 'Craft Beer', 'Cooking Class', 'Food Market', 'Restaurant'],
            'Business V2' => ['Conference', 'Workshop', 'Networking', 'Seminar', 'Trade Show', 'Meetup'],
            'Technology V2' => ['AI/ML', 'Web Dev', 'Mobile', 'Blockchain', 'Gaming', 'Startup'],
            'Nightlife V2' => ['Club Night', 'Rave', 'Bar Event', 'Pool Party', 'Beach Party', 'VIP'],
            'Outdoor V2' => ['Hiking', 'Camping', 'Water Sports', 'Cycling Tour', 'Adventure', 'Nature Walk'],
        ];
        
        $subcategories = collect();
        
        foreach ($categories as $category) {
            if (isset($subcategoryMap[$category->name])) {
                foreach ($subcategoryMap[$category->name] as $subName) {
                    $subcategory = SubCategory::firstOrCreate(
                        [
                            'category_id' => $category->id,
                            'slug' => Str::slug($subName),
                        ],
                        [
                            'name' => $subName,
                        ]
                    );
                    $subcategories->push($subcategory);
                }
            }
        }
        
        return $subcategories;
    }
    
    /**
     * Create venues in major Dutch cities.
     */
    private function createVenues()
    {
        $venueData = [
            ['name' => 'Amsterdam Arena', 'address' => 'Arena Boulevard 1', 'city' => 'Amsterdam', 'capacity' => 55000],
            ['name' => 'Rotterdam Ahoy', 'address' => 'Ahoyweg 10', 'city' => 'Rotterdam', 'capacity' => 15000],
            ['name' => 'Utrecht Jaarbeurs', 'address' => 'Jaarbeursplein 6', 'city' => 'Utrecht', 'capacity' => 10000],
            ['name' => 'Eindhoven Evoluon', 'address' => 'Noord-Brabantlaan 1A', 'city' => 'Eindhoven', 'capacity' => 2000],
            ['name' => 'Groningen MartiniPlaza', 'address' => 'Leonard Springerlaan 2', 'city' => 'Groningen', 'capacity' => 4000],
            ['name' => 'Maastricht MECC', 'address' => 'Forum 100', 'city' => 'Maastricht', 'capacity' => 8000],
            ['name' => 'Leiden Stadsgehoorzaal', 'address' => 'Breestraat 60', 'city' => 'Leiden', 'capacity' => 900],
            ['name' => 'Delft Theater de Veste', 'address' => 'Molslaan 40', 'city' => 'Delft', 'capacity' => 500],
            ['name' => 'Haarlem Philharmonie', 'address' => 'Philipsplein 1', 'city' => 'Haarlem', 'capacity' => 750],
            ['name' => 'The Hague World Forum', 'address' => 'Johan van Oldenbarneveltlaan 347', 'city' => 'The Hague', 'capacity' => 5000],
        ];
        
        $venues = collect();
        
        foreach ($venueData as $data) {
            $venue = Venue::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, [
                    'latitude' => $this->getCityCoordinates($data['city'])['lat'],
                    'longitude' => $this->getCityCoordinates($data['city'])['lng'],
                    'contact_email' => 'info@' . Str::slug($data['name']) . '.nl',
                    'contact_phone' => '+31 20 ' . str_pad(rand(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                ])
            );
            $venues->push($venue);
        }
        
        return $venues;
    }
    
    /**
     * Create 15 users with varied profiles.
     */
    private function createUsers()
    {
        // Create regular users
        $users = User::factory(15)->create();
        
        // Add some specific test users
        $testUsers = [
            [
                'name' => 'John Organizer',
                'email' => 'organizer@example.com',
                'role' => User::ROLE_ORGANISER,
                'profile_type' => User::PROFILE_ORGANIZER,
                'account_type' => User::ACCOUNT_PREMIUM,
            ],
            [
                'name' => 'Jane Talent',
                'email' => 'talent@example.com',
                'role' => User::ROLE_USER,
                'profile_type' => User::PROFILE_TALENT,
                'account_type' => User::ACCOUNT_FREE,
            ],
        ];
        
        foreach ($testUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'status' => User::STATUS_ACTIVE,
                    'country' => 'NL',
                ])
            );
            $users->push($user);
        }
        
        return $users;
    }
    
    /**
     * Create 6 events for each user.
     */
    private function createEvents($users, $categories, $venues)
    {
        $events = collect();
        
        foreach ($users as $user) {
            // Mix of different event types for each user
            $userEvents = collect();
            
            // 2 upcoming events
            $userEvents = $userEvents->merge(
                EventV2::factory(2)->upcoming()->approved()->create(['user_id' => $user->id])
            );
            
            // 1 live event (today)
            $userEvents = $userEvents->merge(
                EventV2::factory(1)->live()->approved()->create(['user_id' => $user->id])
            );
            
            // 2 past events
            $userEvents = $userEvents->merge(
                EventV2::factory(2)->past()->approved()->create(['user_id' => $user->id])
            );
            
            // 1 random status (could be draft, cancelled, or pending)
            $userEvents = $userEvents->merge(
                EventV2::factory(1)->create(['user_id' => $user->id])
            );
            
            // Attach random subcategories to events
            foreach ($userEvents as $event) {
                $subcategories = SubCategory::where('category_id', $event->category_id)
                    ->inRandomOrder()
                    ->take(rand(1, 3))
                    ->pluck('id');
                
                $event->subcategories()->attach($subcategories);
            }
            
            $events = $events->merge($userEvents);
        }
        
        return $events;
    }
    
    /**
     * Create wishlist relationships.
     */
    private function createWishlists($users, $events)
    {
        foreach ($users as $user) {
            // Each user wishlists 5-15 random events
            $wishlistCount = rand(5, 15);
            
            // Get events not created by this user
            $availableEvents = $events->where('user_id', '!=', $user->id);
            
            // Randomly select events
            $eventsToWishlist = $availableEvents->random(
                min($wishlistCount, $availableEvents->count())
            );
            
            foreach ($eventsToWishlist as $event) {
                Wishlist::firstOrCreate([
                    'user_id' => $user->id,
                    'event_v2_id' => $event->id,
                ], [
                    'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
                ]);
            }
        }
        
        // Create some popular events (30+ wishlists)
        $popularEvents = $events->random(3);
        foreach ($popularEvents as $event) {
            $wishlistUsers = $users->where('user_id', '!=', $event->user_id)->random(30);
            
            foreach ($wishlistUsers as $user) {
                Wishlist::firstOrCreate([
                    'user_id' => $user->id,
                    'event_v2_id' => $event->id,
                ]);
            }
        }
    }
    
    /**
     * Create some analytics data.
     */
    private function createAnalytics($events)
    {
        foreach ($events as $event) {
            // Random views (50-500)
            $views = rand(50, 500);
            for ($i = 0; $i < $views; $i++) {
                $event->views()->create([
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'viewed_at' => fake()->dateTimeBetween('-30 days', 'now'),
                ]);
            }
            
            // Random likes (10-100)
            $likes = rand(10, 100);
            $likingUsers = User::inRandomOrder()->take($likes)->pluck('id');
            
            foreach ($likingUsers as $userId) {
                $event->likes()->firstOrCreate([
                    'user_id' => $userId,
                ], [
                    'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
                ]);
            }
        }
    }
    
    /**
     * Get coordinates for Dutch cities.
     */
    private function getCityCoordinates($city)
    {
        $coordinates = [
            'Amsterdam' => ['lat' => 52.3676, 'lng' => 4.9041],
            'Rotterdam' => ['lat' => 51.9244, 'lng' => 4.4777],
            'The Hague' => ['lat' => 52.0799, 'lng' => 4.3113],
            'Utrecht' => ['lat' => 52.0907, 'lng' => 5.1214],
            'Eindhoven' => ['lat' => 51.4416, 'lng' => 5.4697],
            'Groningen' => ['lat' => 53.2194, 'lng' => 6.5665],
            'Maastricht' => ['lat' => 50.8514, 'lng' => 5.6910],
            'Leiden' => ['lat' => 52.1601, 'lng' => 4.4970],
            'Delft' => ['lat' => 52.0116, 'lng' => 4.3571],
            'Haarlem' => ['lat' => 52.3874, 'lng' => 4.6461],
        ];
        
        return $coordinates[$city] ?? ['lat' => 52.0, 'lng' => 5.0];
    }
}
