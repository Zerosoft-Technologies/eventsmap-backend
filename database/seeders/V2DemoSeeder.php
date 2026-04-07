<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\EventV2;
use App\Models\User;
use App\Models\Venue;
use App\Models\Wishlist;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * V2DemoSeeder - Creates demo data specifically for Events V2.
 * 
 * This seeder is designed to work alongside existing seeders
 * without conflicts by using unique names and slugs.
 */
class V2DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting V2DemoSeeder...');
        
        // Step 1: Create V2 Categories (with unique slugs)
        $this->command->info('📂 Creating V2 categories...');
        $categories = $this->createV2Categories();
        
        // Step 2: Create V2 Subcategories
        $this->command->info('📋 Creating V2 subcategories...');
        $subcategories = $this->createV2Subcategories($categories);
        
        // Step 3: Create V2 Venues
        $this->command->info('🏛️  Creating V2 venues...');
        $venues = $this->createV2Venues();
        
        // Step 4: Create V2 Users
        $this->command->info('👥 Creating 15 V2 users...');
        $users = $this->createV2Users();
        
        // Step 5: Create V2 Events (6 per user)
        $this->command->info('🎉 Creating 90 V2 events (6 per user)...');
        $events = $this->createV2Events($users, $categories, $venues);
        
        // Step 6: Create Wishlist relationships
        $this->command->info('❤️  Creating wishlist relationships...');
        $this->createWishlists($users, $events);
        
        // Step 7: Add some event analytics
        $this->command->info('📊 Adding event analytics...');
        $this->createAnalytics($events);
        
        $this->command->info('✅ V2DemoSeeder completed successfully!');
        $this->command->info('📈 Summary:');
        $this->command->info("   - Users: {$users->count()}");
        $this->command->info("   - Events: {$events->count()}");
        $this->command->info("   - Wishlists: " . Wishlist::count());
        $this->command->info("   - Categories: {$categories->count()}");
        $this->command->info("   - Venues: {$venues->count()}");
    }
    
    /**
     * Create V2 event categories using the same data as CategorySeeder.
     */
    private function createV2Categories()
    {
        // Same categories as CategorySeeder
        $categoriesData = [
            "talent" => "Talent",
            "venue" => "Venue",
            "nightlife" => "Nightlife",
            "film" => "Film",
            "theatre" => "Theatre",
            "dance" => "Dance",
            "community" => "Community",
            "music" => "Music",
            "sports" => "Sports",
        ];
        
        $categories = collect();
        
        foreach ($categoriesData as $slug => $name) {
            $category = Category::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => "Events related to {$name}",
                ]
            );
            $categories->push($category);
        }
        
        return $categories;
    }
    
    /**
     * Create V2 subcategories using the same data as CategorySeeder.
     */
    private function createV2Subcategories($categories)
    {
        // Same subcategories as CategorySeeder
        $categoriesData = [
            "talent" => [
                "Actor", "Actress", "Celebrity", "Circus Artist", "Comic", "Dancer", 
                "DJ/VJ", "Entertainer", "Magician/Illusionist", "Musician", "Performing Animal",
                "Player", "Puppeteer", "Singer", "Other"
            ],
            "venue" => [
                "Bar", "Casino", "Cinema", "Concert Hall", "Government", "Hall", "Hotel",
                "Night Club", "Open Air", "Restaurant", "Stadium", "Theatre", "Other"
            ],
            "nightlife" => [
                "After Midnight Bar", "After Midnight Restaurant", "After Midnight Show",
                "After Party", "Cinema", "Dance", "Dancing", "Dinner Show", "DJ/VJ Session",
                "Karaoke", "Ladies Night", "Late Night Parties", "Live Music", "Live Music Bar",
                "Nightclub", "Parties", "Slots & Gambling", "Theatre", "Comedy Night", "Silent Disco", 
                "Themed Party", "Club Night", "Day Party", "Brunch & Beats", "Sunset Party", "Other"
            ],
            "film" => [
                "Action", "Adventure", "Adult", "Animation", "Biographical", "Children",
                "Comedy", "Crime", "Detective", "Documentary", "Drama", "Educational", "Fantasy", "Horror",
                "Historical", "Musical", "Mystery", "Romance", "Science Fiction", "Short",
                "Sports", "Thriller", "War", "Western", "Other"
            ],
            "theatre" => [
                "Acting", "Cabaret", "Children", "Circus", "Comedy","Dinner show" , "Drama", "Experimental",
                "Farce", "Immersive", "Improvisational", "Magic & Illusion", "Melodrama",
                "Mime", "Musical", "Of The Absurd", "Open Stage", "Opera", "Performance",
                "Play", "Play With Music", "Puppetry", "Revue", "Rock Opera", "Show",
                "Stand Up Comedy", "Storytelling", "Tragedy", "Variety Show", "Other"
            ],
            "dance" => [
                "Bachata","Ballet", "Ballroom", "Belly Dance", "Bharatanatyam", "Break", "Can-Can",
                "Cha-Cha", "Children", "Classical", "Contemporary", "Country", "Folk",
                "Highland", "Hip Hop", "Irish", "Jazz", "Kathak", "Kizomba", "Modern", "Pole",
                "Rumba", "Salsa", "Street", "Swing", "Tap", "Other"
            ],
            "community" => [
                "Carnival", "Children", "Christmas Market", "Fair", "Festival", "Field Day",
                "Food & Drink", "Light Show", "Neighbourhood", "Parade", "Fireworks", 
                "Light Show / Drone Show", "City Sports Festival", "Games & Quiz", "Outdoor Cinema", 
                "Beach Party", "New Year’s Eve", "Family Events", "Open-Air Party", "Other"
            ],
            "music" => [
                "Alternative", "Ambient", "Blues", "Children", "Classic Pop", "Concert",
                "Contemporary", "Country", "Disco", "DJ/VJ", "Drum & Bass", "EDM Electronic Dance Music",
                "Folk", "Funk", "Garage", "Hip Hop", "House", "Indie", "Jazz", "Latin",
                "Metal", "Pop", "Punk", "R&B", "Reggae", "Rock", "Rock 'n Roll", "Soul",
                "Techno", "Trance", "World", "Other"
            ],
            "sports" => [
                "American Football", "Athletics", "Badminton", "Baseball", "Basketball",
                "Bowling", "Boxing", "Cricket", "Cycling", "Darts", "E Sports", "Fencing",
                "Football", "Golf", "Gymnastics", "Handball", "Hockey", "Horse Racing",
                "Martial Arts", "Motor Sports", "Netball", "Rugby", "Skiing", "Snooker",
                "Squash", "Swimming", "Table Tennis", "Tennis", "Volleyball", "Weightlifting",
                "Wrestling", "Other"
            ]
        ];
        
        $subcategories = collect();
        
        foreach ($categories as $category) {
            if (isset($categoriesData[$category->slug])) {
                foreach ($categoriesData[$category->slug] as $subName) {
                    $slug = Str::slug($subName);
                    
                    // Check if subcategory already exists
                    $subcategory = SubCategory::where('category_id', $category->id)
                        ->where('slug', $slug)
                        ->first();
                    
                    if (!$subcategory) {
                        $subcategory = SubCategory::create([
                            'category_id' => $category->id,
                            'name' => $subName,
                            'slug' => $slug,
                        ]);
                    }
                    
                    $subcategories->push($subcategory);
                }
            }
        }
        
        return $subcategories;
    }
    
    /**
     * Create V2 venues with unique names.
     */
    private function createV2Venues()
    {
        $venueData = [
            ['name' => 'V2 Amsterdam Music Hall', 'address' => 'Arena Boulevard 1', 'city' => 'Amsterdam', 'capacity' => 55000],
            ['name' => 'V2 Rotterdam Event Center', 'address' => 'Ahoyweg 10', 'city' => 'Rotterdam', 'capacity' => 15000],
            ['name' => 'V2 Utrecht Convention Hall', 'address' => 'Jaarbeursplein 6', 'city' => 'Utrecht', 'capacity' => 10000],
            ['name' => 'V2 Eindhoven Tech Hub', 'address' => 'Noord-Brabantlaan 1A', 'city' => 'Eindhoven', 'capacity' => 2000],
            ['name' => 'V2 Groningen Arena', 'address' => 'Leonard Springerlaan 2', 'city' => 'Groningen', 'capacity' => 4000],
            ['name' => 'V2 Maastricht Expo', 'address' => 'Forum 100', 'city' => 'Maastricht', 'capacity' => 8000],
            ['name' => 'V2 Leiden Theater', 'address' => 'Breestraat 60', 'city' => 'Leiden', 'capacity' => 900],
            ['name' => 'V2 Delft Cultural Center', 'address' => 'Molslaan 40', 'city' => 'Delft', 'capacity' => 500],
            ['name' => 'V2 Haarlem Concert Hall', 'address' => 'Philipsplein 1', 'city' => 'Haarlem', 'capacity' => 750],
            ['name' => 'V2 The Hague Forum', 'address' => 'Johan van Oldenbarneveltlaan 347', 'city' => 'The Hague', 'capacity' => 5000],
        ];
        
        $venues = collect();
        
        foreach ($venueData as $data) {
            $venue = Venue::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, [
                    'slug' => Str::slug($data['name']),
                    'latitude' => $this->getCityCoordinates($data['city'])['lat'],
                    'longitude' => $this->getCityCoordinates($data['city'])['lng'],
                    'country' => 'NL',
                    'email' => 'info@' . Str::slug($data['name']) . '.nl',
                    'phone' => '+31 20 ' . str_pad(rand(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                    'website' => 'https://' . Str::slug($data['name']) . '.nl',
                    'description' => 'Premium V2 venue located in ' . $data['city'],
                    'is_active' => true,
                ])
            );
            $venues->push($venue);
        }
        
        return $venues;
    }
    
    /**
     * Create V2 users.
     */
    private function createV2Users()
    {
        $users = collect();
        
        // Create regular V2 users
        for ($i = 1; $i <= 15; $i++) {
            $user = User::firstOrCreate(
                ['email' => "v2user{$i}@example.com"],
                [
                    'name' => "V2 User {$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(10),
                    'role' => User::ROLE_USER,
                    'is_active' => true,
                    'profile_type' => [User::PROFILE_EVENT, User::PROFILE_TALENT, User::PROFILE_ORGANIZER, User::PROFILE_VENUE][array_rand([User::PROFILE_EVENT, User::PROFILE_TALENT, User::PROFILE_ORGANIZER, User::PROFILE_VENUE])],
                    'account_type' => [User::ACCOUNT_FREE, User::ACCOUNT_PREMIUM][array_rand([User::ACCOUNT_FREE, User::ACCOUNT_PREMIUM])],
                    'status' => User::STATUS_ACTIVE,
                    'billing_type' => ['private', 'business'][array_rand(['private', 'business'])],
                    'country' => 'NL',
                    'vat_number' => rand(0, 1) ? 'NL' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT) . 'B' . rand(10, 99) : null,
                    'stripe_subscription_id' => rand(0, 1) ? Str::uuid() : null,
                    'created_at' => now()->subDays(rand(1, 365)),
                ]
            );
            $users->push($user);
        }
        
        // Add some specific test users
        $testUsers = [
            [
                'name' => 'V2 John Organizer',
                'email' => 'v2organizer@example.com',
                'role' => User::ROLE_USER,
                'profile_type' => User::PROFILE_ORGANIZER,
                'account_type' => User::ACCOUNT_PREMIUM,
            ],
            [
                'name' => 'V2 Jane Talent',
                'email' => 'v2talent@example.com',
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
                    'billing_type' => 'private',
                ])
            );
            $users->push($user);
        }
        
        return $users;
    }
    
    /**
     * Create V2 events.
     */
    private function createV2Events($users, $categories, $venues)
    {
        $events = collect();
        $totalEvents = $users->count() * 6;
        $downloadedCount = 0;
        
        $this->command->info("📸 Downloading {$totalEvents} cover images from picsum.photos...");
        
        foreach ($users as $userIndex => $user) {
            // Mix of different event types for each user
            $userEvents = collect();
            
            // 2 upcoming events
            $userEvents = $userEvents->merge(
                EventV2::factory(2)->state(['user_id' => $user->id])->upcoming()->create()
            );
            
            // 1 live event (today)
            $userEvents = $userEvents->merge(
                EventV2::factory(1)->state(['user_id' => $user->id])->live()->create()
            );
            
            // 2 past events
            $userEvents = $userEvents->merge(
                EventV2::factory(2)->state(['user_id' => $user->id])->past()->create()
            );
            
            // 1 random status (could be draft, cancelled, or pending)
            $userEvents = $userEvents->merge(
                EventV2::factory(1)->state(['user_id' => $user->id])->create()
            );
            
            // Download images and attach subcategories for each event
            foreach ($userEvents as $event) {
                // Download and store cover image
                $imagePath = $this->downloadDemoImage();
                if ($imagePath) {
                    $event->update(['image_path' => $imagePath]);
                    $downloadedCount++;
                }
                
                // Attach random subcategories
                $subcategories = SubCategory::where('category_id', $event->category_id)
                    ->inRandomOrder()
                    ->take(rand(1, 3))
                    ->pluck('id');
                
                if ($subcategories->isNotEmpty()) {
                    $event->subcategories()->attach($subcategories);
                }
            }
            
            // Progress indicator
            $this->command->info("   User " . ($userIndex + 1) . "/{$users->count()} - {$downloadedCount}/{$totalEvents} images downloaded");
            
            $events = $events->merge($userEvents);
        }
        
        $this->command->info("✅ Downloaded {$downloadedCount}/{$totalEvents} cover images successfully");
        
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
            
            if ($availableEvents->isNotEmpty()) {
                // Randomly select events
                $eventsToWishlist = $availableEvents->random(
                    min($wishlistCount, $availableEvents->count())
                );
                
                foreach ($eventsToWishlist as $event) {
                    Wishlist::firstOrCreate([
                        'user_id' => $user->id,
                        'event_v2_id' => $event->id,
                    ], [
                        'created_at' => now()->subDays(rand(1, 90)),
                    ]);
                }
            }
        }
        
        // Create some popular events (30+ wishlists)
        if ($events->isNotEmpty()) {
            $popularEvents = $events->random(min(3, $events->count()));
            foreach ($popularEvents as $event) {
                $wishlistUsers = $users->where('user_id', '!=', $event->user_id)->random(min(30, $users->count() - 1));
                
                foreach ($wishlistUsers as $user) {
                    Wishlist::firstOrCreate([
                        'user_id' => $user->id,
                        'event_v2_id' => $event->id,
                    ]);
                }
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
                    'ip_address' => rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255),
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'viewed_at' => now()->subDays(rand(0, 30)),
                ]);
            }
            
            // Random likes (10-100)
            $likes = rand(10, 100);
            $likingUsers = User::inRandomOrder()->take($likes)->pluck('id');
            
            foreach ($likingUsers as $userId) {
                $event->likes()->firstOrCreate([
                    'user_id' => $userId,
                ], [
                    'created_at' => now()->subDays(rand(0, 30)),
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
    
    /**
     * Download a demo image from picsum.photos and store it locally.
     * 
     * @return string|null The relative storage path or null on failure
     */
    private function downloadDemoImage(): ?string
    {
        try {
            // Ensure the directory exists
            $directory = 'events/demo';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
            
            // Generate unique filename
            $filename = Str::uuid() . '.jpg';
            $relativePath = $directory . '/' . $filename;
            
            // Download image from picsum.photos (800x600 random image)
            $response = Http::timeout(10)->get('https://picsum.photos/800/600');
            
            if ($response->successful()) {
                // Store the image
                Storage::disk('public')->put($relativePath, $response->body());
                return $relativePath;
            }
            
            return null;
        } catch (\Exception $e) {
            // Log the error but don't break seeding
            $this->command->warn("⚠️  Failed to download image: " . $e->getMessage());
            return null;
        }
    }
}
