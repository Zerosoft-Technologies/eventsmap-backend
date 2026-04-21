<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\EventV2;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Database\Factories\Support\CategoryImageProvider;
use Database\Factories\Support\CityDataProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventV2>
 */
class EventV2Factory extends Factory
{
    protected $model = EventV2::class;

    /** @var list<string> */
    private const TIME_SLOTS = [
        '08:00:00', '09:00:00', '10:00:00', '11:00:00', '12:00:00', '13:00:00', '14:00:00',
        '15:00:00', '17:00:00', '18:00:00', '19:00:00', '20:00:00', '21:00:00', '22:00:00', '23:00:00',
    ];

    /** @var list<string> */
    private const TITLES = [
        'Neon Nights DJ Festival', 'Morning Yoga in the Park', 'Tech Startup Summit 2026',
        'The Comedy Vault Live', 'Street Food Weekend', 'Salsa Under the Stars',
        'Indie Film Screening Night', 'Business Breakfast Club', 'Halloween Rave',
        'Christmas Market Opening', 'Jazz & Wine Evening', 'Kids Science Fair',
        'Open Mic Night at The Loft', 'Marathon Training Kickoff', 'Photography Masterclass',
        'Sunset Rooftop Sessions', 'Classical Strings in the Square', 'Boardgames & Brews',
        'Urban Art Walk', 'Cycling Meetup & Coffee', 'Craft Beer Tasting Tour',
        'Founders Pitch Night', 'AI Ethics Panel', 'Vinyl Listening Party',
        'Karaoke Championship', 'Stand-up Showcase', 'Tapas & Flamenco Evening',
        'Poetry Slam Finals', 'Hackathon Weekend', 'Design Sprint Day',
        'Farmers Market Live Music', 'Reggae on the Canal', 'House Music Marathon',
        'Wine & Paint Night', 'Charity Fun Run', 'Basketball 3v3 Tournament',
        'Esports Finals Watch Party', 'Retro Arcade Night', 'Sourdough Workshop',
        'Mindfulness Retreat Day', 'Cocktail Masterclass', 'Tango Social',
        'Book Club Meet & Greet', 'Drone Light Show', 'Lantern Festival Parade',
        'Metal Night: Rising Bands', 'Soul & Funk Revue', 'Brunch & Beats',
        'Startup Hiring Mixer', 'Women in Tech Breakfast', 'Climate Action Forum',
        'Street Dance Battle', 'Choir in the Park', 'Silent Disco Walk',
        'Food Truck Rally', 'Gin Garden Party', 'Whisky Tasting Masterclass',
        'Indie Game Jam', 'Boardroom to Ballroom Gala', 'Rooftop Cinema Classics',
        'Sustainability Fair', 'Zero Waste Workshop', 'Pet Adoption Day',
        'Flower Arranging Class', 'Pottery Wheel Night', 'Astronomy Open Night',
        'Beach Cleanup & BBQ', 'Surf Film Festival', 'Skate Jam Session',
        'Craft Market & DJs', 'Bollywood Dance Night', 'Afrobeat Live',
        'Bluegrass Picnic', 'Opera Highlights', 'Symphony in the Park',
        'Chess Tournament', 'Speed Dating Social', 'Language Exchange Café',
        'Improv Theatre Jam', 'Magic & Mentalism Night', 'Beatmaker Workshop',
        'Candlelit Acoustic Set', 'Neon Run 10K', 'Winter Ice Skating Social',
        'Spring Fashion Pop-up', 'Vintage Fair & Live Band', 'Community Choir Auditions',
        'Kids Theatre Workshop', 'Teen Band Battle', 'Fathers Day Jazz Brunch',
        'Mothers Day Flower Market', 'Pride Block Party', 'Diwali Lights Festival',
        'New Years Eve Countdown', 'Valentines Jazz Dinner', 'Easter Egg Hunt & Concert',
        'Summer Solstice Party', 'Autumn Harvest Feast', 'Oktoberfest Weekend',
        'Hip-hop Cypher Night', 'Lo-fi Study Session Live', 'Chiptune Arcade Rave',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(self::TITLES).' '.fake()->lexify('???');
        $slug = Str::slug($title).'-'.Str::lower(Str::replace('-', '', (string) Str::uuid()));
        $city = CityDataProvider::getUniqueCoordinates(CityDataProvider::getWeightedCity());

        $roll = fake()->numberBetween(1, 100);
        if ($roll <= 20) {
            $eventDate = Carbon::parse(fake()->dateTimeBetween('-6 months', '-1 day'));
        } elseif ($roll <= 50) {
            $eventDate = Carbon::parse(fake()->dateTimeBetween('+1 day', '+6 months'));
        } else {
            $eventDate = Carbon::parse(fake()->dateTimeBetween('now', '+7 days'));
        }

        $schedule = $this->buildSchedule($eventDate, fake()->randomElement(self::TIME_SLOTS), fake()->numberBetween(1, 4));

        $entranceStatus = $this->weightedEntranceStatus();
        $isPast = $eventDate->lt(Carbon::today());

        if ($isPast) {
            $status = fake()->boolean(10) ? EventV2::STATUS_CANCELLED : EventV2::STATUS_COMPLETED;
        } elseif (fake()->numberBetween(1, 100) <= 5) {
            $status = EventV2::STATUS_SUSPENDED;
        } else {
            $status = EventV2::STATUS_UPCOMING;
        }

        $adminId = User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->inRandomOrder()->value('id');

        $isApproved = $isPast
            ? true
            : ($status === EventV2::STATUS_SUSPENDED ? fake()->boolean(40) : fake()->boolean(80));

        $categoryId = Category::query()->inRandomOrder()->value('id')
            ?? Category::query()->value('id');
        $categoryName = $categoryId !== null
            ? self::getCategoryName((int) $categoryId)
            : 'Music';
        $imageUrl = CategoryImageProvider::getImageUrlFromTitle($title, $categoryName);
        $additionalImages = fake()->boolean(60)
            ? CategoryImageProvider::getAdditionalImageUrls($categoryName, fake()->numberBetween(1, 3))
            : [];

        return [
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'title' => $title,
            'slug' => $slug,
            'event_type' => fake()->randomElement(['free', 'premium']),
            'category_id' => $categoryId,
            'subcategory_ids' => function (array $attributes): ?array {
                $categoryId = $attributes['category_id'] ?? null;
                if ($categoryId === null) {
                    return null;
                }

                return SubCategory::query()
                    ->where('category_id', $categoryId)
                    ->inRandomOrder()
                    ->limit(fake()->numberBetween(1, 3))
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all();
            },
            'event_date' => $eventDate->toDateString(),
            'start_date' => $schedule['start_date'],
            'end_date' => $schedule['end_date'],
            'start_time' => $schedule['start_time'],
            'end_time' => $schedule['end_time'],
            'start_datetime' => $schedule['start_datetime'],
            'end_datetime' => $schedule['end_datetime'],
            'address' => CityDataProvider::randomFormattedAddress($city),
            'latitude' => $city['lat'],
            'longitude' => $city['lng'],
            'dress_code' => fake()->randomElement(EventV2::DRESS_CODES),
            'age_limit' => fake()->randomElement(EventV2::AGE_LIMITS),
            'entrance_status' => $entranceStatus,
            'entrance_fee' => $entranceStatus === EventV2::ENTRANCE_PAID
                ? fake()->randomFloat(2, 5, 150)
                : null,
            'condition_entrance_fee' => fake()->boolean(40) ? fake()->boolean() : null,
            'condition_dress_code' => fake()->boolean(30) ? fake()->sentence() : null,
            'condition_age_limit' => fake()->boolean(30) ? fake()->sentence() : null,
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->companyEmail(),
            'contact_website' => fake()->url(),
            'ticket_url' => fake()->boolean(50) ? fake()->url() : null,
            'booking_instructions' => fake()->boolean(40) ? fake()->sentence() : null,
            'description' => implode("\n\n", fake()->paragraphs(3)),
            'contact_box_message' => fake()->boolean(30) ? fake()->sentence() : null,
            'venue_details' => fake()->boolean(40) ? fake()->sentence() : null,
            'image_path' => $imageUrl,
            'additional_images' => $additionalImages,
            'facebook_url' => 'https://facebook.com/events/'.fake()->numerify('##########'),
            'instagram_url' => 'https://instagram.com/'.fake()->slug(),
            'tiktok_url' => fake()->boolean(40) ? 'https://tiktok.com/@'.fake()->slug() : null,
            'venue_id' => fake()->boolean(50)
                ? (Venue::query()->where('city', $city['city'])->inRandomOrder()->value('id') ?? Venue::query()->inRandomOrder()->value('id'))
                : null,
            'status' => $status,
            'is_free_package' => fake()->boolean(40),
            'view_count' => fake()->numberBetween(0, 5000),
            'like_count' => function (array $attributes): int {
                $views = (int) ($attributes['view_count'] ?? 0);

                return fake()->numberBetween(0, $views);
            },
            'is_approved' => $isApproved,
            'approved_at' => $isApproved ? Carbon::parse(fake()->dateTimeBetween('-3 months', 'now')) : null,
            'approved_by' => $isApproved ? $adminId : null,
            'suspension_reason' => $status === EventV2::STATUS_SUSPENDED ? fake()->sentence() : null,
            'suspended_at' => $status === EventV2::STATUS_SUSPENDED ? Carbon::parse(fake()->dateTimeBetween('-1 month', 'now')) : null,
            'suspended_by' => $status === EventV2::STATUS_SUSPENDED ? $adminId : null,
            'is_recurring' => fake()->boolean(15),
            'is_copy_event' => fake()->boolean(10),
            'show_upcoming_events' => fake()->boolean(75),
            'show_past_events' => fake()->boolean(55),
            'event_option' => fake()->optional(0.4)->randomElement(['single_day', 'multi_day', 'festival_pass', 'vip_addon']),
            'invited_talents' => $this->randomUserIdList(0, 3),
            'invited_organisers' => $this->randomUserIdList(0, 2),
            'invited_venues' => $this->randomVenueIdList(0, 1),
        ];
    }

    public function past(): static
    {
        return $this->state(function (): array {
            $eventDate = Carbon::parse(fake()->dateTimeBetween('-6 months', '-1 day'));
            $schedule = $this->buildSchedule($eventDate, fake()->randomElement(self::TIME_SLOTS), fake()->numberBetween(1, 4));
            $adminId = User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->inRandomOrder()->value('id');
            $cancelled = fake()->boolean(10);

            return [
                'event_date' => $eventDate->toDateString(),
                'start_date' => $schedule['start_date'],
                'end_date' => $schedule['end_date'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'start_datetime' => $schedule['start_datetime'],
                'end_datetime' => $schedule['end_datetime'],
                'status' => $cancelled ? EventV2::STATUS_CANCELLED : EventV2::STATUS_COMPLETED,
                'is_approved' => true,
                'approved_at' => Carbon::parse(fake()->dateTimeBetween('-3 months', 'now')),
                'approved_by' => $adminId,
                'suspension_reason' => null,
                'suspended_at' => null,
                'suspended_by' => null,
            ];
        });
    }

    public function upcoming(): static
    {
        return $this->state(function (): array {
            $eventDate = Carbon::parse(fake()->dateTimeBetween('+1 day', '+6 months'));
            $schedule = $this->buildSchedule($eventDate, fake()->randomElement(self::TIME_SLOTS), fake()->numberBetween(1, 4));
            $adminId = User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->inRandomOrder()->value('id');
            $isApproved = fake()->boolean(80);

            return [
                'event_date' => $eventDate->toDateString(),
                'start_date' => $schedule['start_date'],
                'end_date' => $schedule['end_date'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'start_datetime' => $schedule['start_datetime'],
                'end_datetime' => $schedule['end_datetime'],
                'status' => EventV2::STATUS_UPCOMING,
                'is_approved' => $isApproved,
                'approved_at' => $isApproved ? Carbon::parse(fake()->dateTimeBetween('-3 months', 'now')) : null,
                'approved_by' => $isApproved ? $adminId : null,
                'suspension_reason' => null,
                'suspended_at' => null,
                'suspended_by' => null,
            ];
        });
    }

    public function live(): static
    {
        return $this->state(function (): array {
            $eventDate = Carbon::today();
            $start = Carbon::now()->subMinutes(fake()->numberBetween(20, 90))->setSeconds(0);
            $end = (clone $start)->addHours(fake()->numberBetween(2, 5));
            $schedule = $this->buildScheduleFromInstants($eventDate, $start, $end);
            $adminId = User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->inRandomOrder()->value('id');

            return [
                'event_date' => $eventDate->toDateString(),
                'start_date' => $schedule['start_date'],
                'end_date' => $schedule['end_date'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'start_datetime' => $schedule['start_datetime'],
                'end_datetime' => $schedule['end_datetime'],
                'status' => EventV2::STATUS_LIVE,
                'is_approved' => true,
                'approved_at' => Carbon::parse(fake()->dateTimeBetween('-2 months', 'now')),
                'approved_by' => $adminId,
                'suspension_reason' => null,
                'suspended_at' => null,
                'suspended_by' => null,
            ];
        });
    }

    public function suspended(): static
    {
        return $this->state(function (): array {
            $eventDate = Carbon::parse(fake()->dateTimeBetween('+1 day', '+3 months'));
            $schedule = $this->buildSchedule($eventDate, fake()->randomElement(self::TIME_SLOTS), fake()->numberBetween(1, 3));
            $adminId = User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->inRandomOrder()->value('id');

            return [
                'event_date' => $eventDate->toDateString(),
                'start_date' => $schedule['start_date'],
                'end_date' => $schedule['end_date'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'start_datetime' => $schedule['start_datetime'],
                'end_datetime' => $schedule['end_datetime'],
                'status' => EventV2::STATUS_SUSPENDED,
                'is_approved' => fake()->boolean(40),
                'approved_at' => fake()->boolean(40) ? Carbon::parse(fake()->dateTimeBetween('-3 months', 'now')) : null,
                'approved_by' => fake()->boolean(40) ? $adminId : null,
                'suspension_reason' => fake()->sentence(),
                'suspended_at' => Carbon::parse(fake()->dateTimeBetween('-1 month', 'now')),
                'suspended_by' => $adminId,
            ];
        });
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'entrance_status' => EventV2::ENTRANCE_PAID,
            'entrance_fee' => fake()->randomFloat(2, 5, 150),
        ]);
    }

    public function overnight(): static
    {
        $date = Carbon::parse(fake()->dateTimeBetween('+1 day', '+2 months'));
        $schedule = $this->buildSchedule($date, '22:00:00', fake()->numberBetween(4, 7));

        return $this->state(fn (): array => [
            'event_date' => $date->toDateString(),
            'start_date' => $schedule['start_date'],
            'end_date' => $schedule['end_date'],
            'start_time' => $schedule['start_time'],
            'end_time' => $schedule['end_time'],
            'start_datetime' => $schedule['start_datetime'],
            'end_datetime' => $schedule['end_datetime'],
        ]);
    }

    public function forCity(string $cityName): static
    {
        return $this->state(function () use ($cityName): array {
            $city = CityDataProvider::getUniqueCoordinates(CityDataProvider::getCityByName($cityName));

            return [
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
                'address' => CityDataProvider::randomFormattedAddress($city),
                'venue_id' => Venue::query()->where('city', $city['city'])->inRandomOrder()->value('id')
                    ?? Venue::query()->inRandomOrder()->value('id'),
            ];
        });
    }

    private function weightedEntranceStatus(): string
    {
        $r = fake()->numberBetween(1, 100);
        if ($r <= 60) {
            return EventV2::ENTRANCE_PAID;
        }
        if ($r <= 85) {
            return EventV2::ENTRANCE_FREE;
        }
        if ($r <= 95) {
            return EventV2::ENTRANCE_SOLD_OUT;
        }

        return EventV2::ENTRANCE_CANCELLED;
    }

    /**
     * @return array{start_date: string, end_date: string, start_time: string, end_time: string, start_datetime: Carbon, end_datetime: Carbon}
     */
    private function buildSchedule(Carbon $eventDate, string $startTime, int $durationHours): array
    {
        $start = Carbon::parse($eventDate->toDateString().' '.$startTime);
        $end = (clone $start)->addHours($durationHours);

        return $this->buildScheduleFromInstants($eventDate, $start, $end);
    }

    /**
     * @return array{start_date: string, end_date: string, start_time: string, end_time: string, start_datetime: Carbon, end_datetime: Carbon}
     */
    private function buildScheduleFromInstants(Carbon $eventDate, Carbon $start, Carbon $end): array
    {
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $end->format('H:i:s'),
            'start_datetime' => $start,
            'end_datetime' => $end,
        ];
    }

    /**
     * @return list<int>|null
     */
    private function randomUserIdList(int $min, int $max): ?array
    {
        $count = fake()->numberBetween($min, $max);
        if ($count === 0) {
            return null;
        }

        $ids = User::query()->inRandomOrder()->limit($count)->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();

        return $ids === [] ? null : $ids;
    }

    /**
     * @return list<int>|null
     */
    private function randomVenueIdList(int $min, int $max): ?array
    {
        $count = fake()->numberBetween($min, $max);
        if ($count === 0) {
            return null;
        }

        $ids = Venue::query()->inRandomOrder()->limit($count)->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();

        return $ids === [] ? null : $ids;
    }

    /** @var array<int, string> */
    private static array $categoryNameCache = [];

    private static function getCategoryName(int $categoryId): string
    {
        if (! isset(self::$categoryNameCache[$categoryId])) {
            self::$categoryNameCache[$categoryId] = Category::find($categoryId)?->name ?? 'Music';
        }

        return self::$categoryNameCache[$categoryId];
    }
}
