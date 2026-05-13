<?php

namespace Database\Factories;

use App\Support\ProfilePublicationStatus;
use App\Support\PublishStatus;
use App\Models\User;
use App\Models\VenueCategory;
use App\Models\VenueSubcategory;
use App\Models\VenueV2;
use Database\Factories\Support\CategoryImageProvider;
use Database\Factories\Support\CityDataProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VenueV2>
 */
class VenueV2Factory extends Factory
{
    protected $model = VenueV2::class;

    public function configure(): static
    {
        return $this->afterCreating(function (VenueV2 $venue): void {
            $catId = $venue->venue_category_id
                ?? VenueCategory::query()->where('slug', VenueCategory::MAIN_SLUG)->value('id');
            if ($catId !== null && $venue->venue_category_id === null) {
                $venue->venue_category_id = (int) $catId;
                $venue->saveQuietly();
            }
            if ($venue->venue_category_id === null) {
                return;
            }
            $subIds = VenueSubcategory::query()
                ->where('venue_category_id', $venue->venue_category_id)
                ->inRandomOrder()
                ->limit(fake()->numberBetween(1, 3))
                ->pluck('id')
                ->all();
            if ($subIds !== []) {
                $venue->venueSubcategories()->sync($subIds);
            }
        });
    }

    /** @var list<string> */
    private const NAMES = [
        'The Grand Pavilion', 'Skyline Rooftop Bar', 'The Old Factory', 'Studio 9', 'Harbour Club',
        'The Loft', 'Meridian Hall', 'Copper Hall', 'North Dock Warehouse', 'The Atrium',
        'Velvet Underground', 'Crystal Ballroom', 'Riverside Arena', 'The Boiler Room',
        'District Hall', 'Lantern Courtyard', 'Echo Chamber', 'The Mezzanine', 'Sunset Terrace',
        'The Foundry', 'Basement Sessions', 'Kings Row Theatre', 'The Rotunda', 'Glasshouse Events',
        'Metro Live Hall', 'The Courtyard', 'Brickyard Social', 'Harbour Lights', 'The Annex',
        'Summit Hall', 'Crossroads Club', 'The Warehouse', 'Lighthouse Stage', 'Orchard Barn',
        'The Colonnade', 'Rooftop 54', 'The Arcade', 'North Star Hall', 'The Gallery Room',
        'Quayside Pavilion', 'The Vault', 'Studio North', 'The Yard', 'Market Hall Live',
        'The Observatory', 'Riverside Loft', 'The Spire', 'Harbour Studio', 'The Greenroom',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(self::NAMES).' '.fake()->lexify('???');
        $slug = Str::slug($title).'-'.Str::lower(Str::replace('-', '', (string) Str::uuid()));
        $city = CityDataProvider::getUniqueCoordinates(CityDataProvider::getWeightedCity());

        $venueCategoryId = VenueCategory::query()->inRandomOrder()->value('id')
            ?? VenueCategory::query()->where('slug', VenueCategory::MAIN_SLUG)->value('id');
        $venueCategoryName = $venueCategoryId !== null
            ? self::getVenueCategoryName((int) $venueCategoryId)
            : 'Venue';

        $allowDogs = fake()->boolean(30);
        $wheelchair = fake()->boolean(60);

        return [
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'status' => ProfilePublicationStatus::DRAFT,
            'publish_status' => PublishStatus::DRAFT,
            'title' => $title,
            'slug' => $slug,
            'event_type' => fake()->randomElement(['free', 'premium']),
            'category_id' => null,
            'subcategory_ids' => null,
            'venue_category_id' => $venueCategoryId !== null ? (int) $venueCategoryId : null,
            'address' => CityDataProvider::randomFormattedAddress($city),
            'latitude' => $city['lat'],
            'longitude' => $city['lng'],
            'image_path' => CategoryImageProvider::getImageUrl($venueCategoryName, 1200, 800),
            'additional_images' => fake()->boolean(70)
                ? CategoryImageProvider::getAdditionalImageUrls(
                    $venueCategoryName,
                    fake()->numberBetween(2, 5),
                    800,
                    600
                )
                : [],
            'description' => implode("\n\n", fake()->paragraphs(2)),
            'description_items' => collect(range(1, fake()->numberBetween(3, 6)))
                ->map(fn (): string => fake()->randomElement([
                    'Capacity: '.fake()->numberBetween(120, 2500).' guests',
                    'Private bar available',
                    'Stage with full AV setup',
                    'Air conditioned',
                    'Outdoor terrace',
                    'Dedicated parking',
                    'Green room for artists',
                    'Cloakroom on site',
                ]))
                ->values()
                ->all(),
            'allow_dogs' => $allowDogs,
            // DB: allowance_of_dogs is varchar(32) — keep under limit (see 2026_04_13 migration).
            'allowance_of_dogs' => $allowDogs
                ? Str::limit(fake()->randomElement([
                    'Small dogs only', 'Leashed only', 'Ground floor patio',
                    'Water bowl provided', 'Max 2 dogs', 'Registered support dogs',
                ]), 32, '')
                : null,
            'wheelchair_accessible' => $wheelchair,
            'accessibility_description' => $wheelchair ? fake()->sentence() : null,
            'parking' => fake()->boolean(50),
            'valet' => fake()->boolean(20),
            'play_area' => fake()->boolean(25),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->companyEmail(),
            'contact_website' => fake()->url(),
            'contact_box_message' => fake()->optional(0.22)->sentence(),
            'contact_box_design_message' => fake()->optional(0.12)->paragraph(),
            'facebook_url' => 'https://facebook.com/'.fake()->slug(),
            'instagram_url' => 'https://instagram.com/'.fake()->slug(),
            'tiktok_url' => 'https://tiktok.com/@'.fake()->slug(),
            'opening_hours' => $this->randomOpeningHours(),
            'show_upcoming_events' => fake()->boolean(80),
            'show_past_events' => fake()->boolean(60),
            'is_approved' => fake()->boolean(70),
        ];
    }

    public function forCity(string $cityName): static
    {
        return $this->state(function () use ($cityName): array {
            $city = CityDataProvider::getUniqueCoordinates(CityDataProvider::getCityByName($cityName));

            return [
                'address' => CityDataProvider::randomFormattedAddress($city),
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
            ];
        });
    }

    /**
     * Demo-friendly contact tab copy, profile hero image, and {@see PublishStatus::PUBLISHED} (DatabaseSeeder path).
     */
    public function seedRichContactPresentation(): static
    {
        return $this->state(function (array $attributes): array {
            $venueCategoryId = $attributes['venue_category_id'] ?? null;
            $venueCategoryName = $venueCategoryId !== null
                ? self::getVenueCategoryName((int) $venueCategoryId)
                : 'Venue';

            return [
                'publish_status' => PublishStatus::PUBLISHED,
                'contact_box_message' => 'Ask about availability, capacities, tech specs, and dry hire.',
                'contact_box_design_message' => "Host your next night with us.\n\n".fake()->paragraph(2),
                'image_path' => CategoryImageProvider::getImageUrl($venueCategoryName, 1200, 800),
            ];
        });
    }

    /** @var array<int, string> */
    private static array $venueCategoryCache = [];

    private static function getVenueCategoryName(int $id): string
    {
        if (! isset(self::$venueCategoryCache[$id])) {
            self::$venueCategoryCache[$id] = VenueCategory::find($id)?->name ?? 'Venue';
        }

        return self::$venueCategoryCache[$id];
    }

    /**
     * @return list<array{day: string, is_open: bool, open: string|null, close: string|null}>
     */
    private function randomOpeningHours(): array
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        return array_map(function (string $day): array {
            $isOpen = fake()->boolean(85);
            if (! $isOpen) {
                return [
                    'day' => $day,
                    'is_open' => false,
                    'open' => null,
                    'close' => null,
                ];
            }

            $openH = fake()->numberBetween(8, 14);
            $closeH = fake()->numberBetween($openH + 4, min(27, $openH + 14));

            return [
                'day' => $day,
                'is_open' => true,
                'open' => sprintf('%02d:%02d', $openH, fake()->randomElement([0, 30])),
                'close' => sprintf('%02d:%02d', $closeH % 24, fake()->randomElement([0, 30])),
            ];
        }, $days);
    }
}
