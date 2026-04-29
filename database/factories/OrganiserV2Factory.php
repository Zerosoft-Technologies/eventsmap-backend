<?php

namespace Database\Factories;

use App\Support\ProfilePublicationStatus;
use App\Models\Category;
use App\Models\OrganiserCategory;
use App\Models\OrganiserV2;
use App\Models\SubCategory;
use App\Models\User;
use Database\Factories\Support\CategoryImageProvider;
use Database\Factories\Support\CityDataProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganiserV2>
 */
class OrganiserV2Factory extends Factory
{
    protected $model = OrganiserV2::class;

    /** @var list<string> */
    private const TITLES = [
        'Nova Events Co.', 'The Social Bureau', 'Pulse Productions', 'Urban Collective', 'Starlight Events',
        'Meridian Experiences', 'Harbour Nights Group', 'Atlas Festival Works', 'Velvet Room Agency',
        'Echo District Promotions', 'Northline Live', 'Circuit & Sound', 'Lumen Field Events',
        'Crimson Stage Co.', 'Skyline Sessions', 'District 9 Productions', 'Golden Hour Collective',
        'Riverfront Promotions', 'Neon Circuit', 'Studio Horizon', 'The Weekend Architects',
        'Open Air Society', 'Block Party Bureau', 'Metro Sound Lab', 'Catalyst Live',
        'Front Row Experiences', 'Tapestry Events', 'Signal & Noise', 'Blue Hour Productions',
        'Summit Social Club', 'Crossroads Live', 'Afterglow Agency', 'Quarterdeck Events',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(self::TITLES).' '.fake()->lexify('???');
        $slug = Str::slug($title).'-'.Str::lower(Str::replace('-', '', (string) Str::uuid()));
        $city = CityDataProvider::getUniqueCoordinates(CityDataProvider::getWeightedCity());
        $organiserCategoryId = OrganiserCategory::query()->inRandomOrder()->value('id')
            ?? OrganiserCategory::query()->value('id');
        $organiserCategoryName = $organiserCategoryId !== null
            ? self::getOrganiserCategoryName((int) $organiserCategoryId)
            : 'Promoter';

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
            'organiser_category_id' => $organiserCategoryId,
            'address' => CityDataProvider::randomFormattedAddress($city),
            'latitude' => $city['lat'],
            'longitude' => $city['lng'],
            'image_path' => CategoryImageProvider::getImageUrl($organiserCategoryName, 800, 600),
            'additional_images' => fake()->boolean(60)
                ? CategoryImageProvider::getAdditionalImageUrls(
                    $organiserCategoryName,
                    fake()->numberBetween(1, 3)
                )
                : [],
            'description' => implode("\n\n", fake()->paragraphs(2)),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->companyEmail(),
            'contact_website' => fake()->url(),
            'facebook_url' => 'https://facebook.com/'.fake()->slug(),
            'instagram_url' => 'https://instagram.com/'.fake()->slug(),
            'tiktok_url' => 'https://tiktok.com/@'.fake()->slug(),
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

    /** @var array<int, string> */
    private static array $organiserCategoryCache = [];

    private static function getOrganiserCategoryName(int $id): string
    {
        if (! isset(self::$organiserCategoryCache[$id])) {
            self::$organiserCategoryCache[$id] = OrganiserCategory::find($id)?->name ?? 'Promoter';
        }

        return self::$organiserCategoryCache[$id];
    }
}
