<?php

namespace Database\Factories;

use App\Support\ProfilePublicationStatus;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\User;
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

        $allowDogs = fake()->boolean(30);
        $wheelchair = fake()->boolean(60);

        return [
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'status' => ProfilePublicationStatus::DRAFT,
            'title' => $title,
            'slug' => $slug,
            'event_type' => fake()->randomElement(['free', 'premium']),
            'category_id' => Category::query()->inRandomOrder()->value('id'),
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
            'address' => CityDataProvider::randomFormattedAddress($city),
            'latitude' => $city['lat'],
            'longitude' => $city['lng'],
            'image_path' => CategoryImageProvider::getImageUrl('Venue', 1200, 800),
            'additional_images' => fake()->boolean(70)
                ? CategoryImageProvider::getAdditionalImageUrls(
                    'Venue',
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
