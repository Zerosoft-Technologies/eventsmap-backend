<?php

namespace Database\Factories;

use App\Support\ProfilePublicationStatus;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\TalentCategory;
use App\Models\TalentV2;
use App\Models\User;
use Database\Factories\Support\CategoryImageProvider;
use Database\Factories\Support\CityDataProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TalentV2>
 */
class TalentV2Factory extends Factory
{
    protected $model = TalentV2::class;

    private const LANGUAGES = [
        'English', 'French', 'Spanish', 'Arabic', 'German',
        'Italian', 'Portuguese', 'Mandarin', 'Hindi', 'Dutch',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefixes = ['DJ', 'MC', 'Live', 'The', 'Comedy by', 'Band:', 'Showcase:', 'Act:'];
        $base = fake()->name();
        $title = fake()->randomElement($prefixes).' '.$base;
        $slug = Str::slug($title).'-'.Str::lower(Str::replace('-', '', (string) Str::uuid()));
        $city = CityDataProvider::getUniqueCoordinates(CityDataProvider::getWeightedCity());
        $talentCategoryId = TalentCategory::query()->inRandomOrder()->value('id')
            ?? TalentCategory::query()->value('id');
        $talentCategoryName = $talentCategoryId !== null
            ? self::getTalentCategoryName((int) $talentCategoryId)
            : 'Musician';

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
                    ->limit(fake()->numberBetween(1, 2))
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all();
            },
            'talent_category_id' => $talentCategoryId,
            'city' => $city['city'],
            'address' => CityDataProvider::randomFormattedAddress($city),
            'latitude' => $city['lat'],
            'longitude' => $city['lng'],
            'image_path' => CategoryImageProvider::getImageUrl($talentCategoryName, 800, 800),
            'additional_images' => fake()->boolean(65)
                ? CategoryImageProvider::getAdditionalImageUrls(
                    $talentCategoryName,
                    fake()->numberBetween(1, 4),
                    800,
                    600
                )
                : [],
            'description' => implode("\n\n", fake()->paragraphs(3)),
            'highlights' => implode(' ', fake()->sentences(3)),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->email(),
            'contact_website' => fake()->url(),
            'facebook_url' => 'https://facebook.com/'.fake()->slug(),
            'instagram_url' => 'https://instagram.com/'.fake()->slug(),
            'tiktok_url' => 'https://tiktok.com/@'.fake()->slug(),
            'fan_club_url' => fake()->boolean(30) ? fake()->url() : null,
            'nationality' => $city['country'],
            'show_nationality' => fake()->boolean(70) ? '1' : '0',
            'age' => (string) fake()->numberBetween(18, 65),
            'show_age' => fake()->boolean(40) ? '1' : '0',
            'languages' => fake()->randomElements(self::LANGUAGES, fake()->numberBetween(1, 3)),
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
                'city' => $city['city'],
                'address' => CityDataProvider::randomFormattedAddress($city),
                'latitude' => $city['lat'],
                'longitude' => $city['lng'],
                'nationality' => $city['country'],
            ];
        });
    }

    /** @var array<int, string> */
    private static array $talentCategoryCache = [];

    private static function getTalentCategoryName(int $id): string
    {
        if (! isset(self::$talentCategoryCache[$id])) {
            self::$talentCategoryCache[$id] = TalentCategory::find($id)?->name ?? 'Musician';
        }

        return self::$talentCategoryCache[$id];
    }
}
