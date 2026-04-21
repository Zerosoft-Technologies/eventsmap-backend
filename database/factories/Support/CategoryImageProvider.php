<?php

namespace Database\Factories\Support;

/**
 * Category-aware placeholder images for seeding.
 *
 * Uses Lorem Flickr (tag-based, no API key) so images match broad themes.
 * (Unsplash Source URLs are deprecated; Picsum is not tag-based — use seed via
 * {@see buildPicsumSeedUrl()} only if you need a fallback without external tags.)
 */
class CategoryImageProvider
{
    /**
     * Map category names to comma-separated Flickr tag groups (first tag is weighted).
     * Keys must match seeded names in categories, organiser_categories, and talent_categories.
     *
     * @var array<string, list<string>>
     */
    private static array $categoryKeywords = [
        // —— Event categories (CategoriesSeeder) ——————————————
        'Music' => [
            'concert,music,live',
            'dj,nightclub,music',
            'festival,music,crowd',
            'band,stage,performance',
            'music,lights,stage',
        ],
        'Dance' => [
            'dance,performance,stage',
            'salsa,dancing,couple',
            'ballet,dancer,graceful',
            'hip-hop,dance,urban',
            'dance,crowd,festival',
        ],
        'Theatre' => [
            'theatre,stage,performance',
            'drama,curtain,stage',
            'opera,performance,elegant',
            'ballet,dance,stage',
            'theatre,actors,spotlight',
        ],
        'Community' => [
            'charity,community,people',
            'volunteer,helping,together',
            'family,kids,fun',
            'community,people,together',
            'social,good,community',
        ],
        'Film' => [
            'cinema,film,screening',
            'movie,theatre,popcorn',
            'film,festival,camera',
            'outdoor,cinema,night',
            'documentary,screening,audience',
        ],
        'Nightlife' => [
            'nightclub,lights,party',
            'rooftop,bar,night',
            'club,dj,crowd',
            'cocktail,bar,night',
            'party,neon,lights',
        ],
        'Talent' => [
            'dj,turntable,music',
            'band,concert,stage',
            'comedy,microphone,stage',
            'dance,performance,stage',
            'microphone,spotlight,stage',
        ],
        'Venue' => [
            'venue,hall,elegant',
            'event,hall,interior',
            'ballroom,elegant,lights',
            'rooftop,venue,city',
            'outdoor,venue,garden',
        ],
        'Organiser' => [
            'event,planning,team',
            'corporate,event,elegant',
            'networking,people,event',
            'startup,business,office',
            'festival,crowd,outdoor',
        ],

        // —— Organiser categories (OrganiserCategorySeeder2) —————
        'Promoter' => [
            'concert,music,crowd',
            'festival,stage,lights',
            'tour,music,band',
            'club,night,party',
            'event,poster,music',
        ],
        'Event Producer' => [
            'stage,lights,production',
            'backstage,event,crew',
            'conference,hall,speaker',
            'theatre,stage,production',
            'festival,production,lights',
        ],
        'Venue Organiser' => [
            'theatre,venue,stage',
            'nightclub,interior,lights',
            'cinema,screen,seats',
            'concert,hall,audience',
            'ballroom,venue,elegant',
        ],
        'Artist / Collective' => [
            'band,stage,performance',
            'dj,club,music',
            'dance,group,performance',
            'theatre,actors,stage',
            'artist,studio,creative',
        ],
        'Festival Organisation' => [
            'festival,crowd,outdoor',
            'music,festival,summer',
            'carnival,festival,colorful',
            'outdoor,festival,people',
            'festival,lights,night',
        ],
        'Cultural Organisation' => [
            'museum,culture,art',
            'art,gallery,exhibition',
            'theatre,culture,building',
            'library,books,culture',
            'exhibition,art,modern',
        ],
        'Brand / Commercial' => [
            'brand,marketing,event',
            'launch,event,modern',
            'corporate,stage,presentation',
            'product,showcase,lights',
            'business,team,office',
        ],
        'Community Organiser' => [
            'community,people,together',
            'open-mic,stage,microphone',
            'local,street,fair',
            'nonprofit,volunteer,people',
            'neighborhood,party,outdoor',
        ],
        'Nightlife Event Brand' => [
            'nightclub,dj,lights',
            'club,crowd,party',
            'underground,club,neon',
            'label,music,vinyl',
            'bar,cocktail,night',
        ],

        // —— Talent categories (TalentCategorySeeder) ————————
        'Singer' => [
            'singer,microphone,stage',
            'concert,vocal,performance',
            'music,singer,lights',
            'jazz,singer,club',
            'pop,concert,crowd',
        ],
        'Musician' => [
            'guitar,music,stage',
            'piano,music,performance',
            'drums,music,band',
            'dj,turntable,club',
            'violin,orchestra,music',
        ],
        'Dancer' => [
            'dancer,performance,stage',
            'ballet,dance,theatre',
            'hip-hop,dance,street',
            'dance,couple,salsa',
            'contemporary,dance,art',
        ],
        'Actor' => [
            'actor,theatre,stage',
            'film,actor,camera',
            'drama,stage,spotlight',
            'television,studio,actor',
            'theatre,performance,mask',
        ],

        // —— Optional aliases (docs / future categories) ———————
        'Sports' => [
            'sports,stadium,crowd',
            'football,match,stadium',
            'basketball,court,game',
            'running,marathon,race',
            'sports,athlete,action',
        ],
        'Food & Drink' => [
            'food,restaurant,table',
            'street,food,market',
            'cocktail,drinks,bar',
            'chef,cooking,kitchen',
            'wine,dinner,elegant',
        ],
    ];

    /**
     * @var list<string>
     */
    private static array $defaultKeywords = [
        'event,people,crowd',
        'celebration,gathering,people',
        'party,event,lights',
        'outdoor,event,social',
        'festival,crowd,fun',
    ];

    /** @var list<string> */
    private static array $usedImages = [];

    public static function getImageUrl(
        string $categoryName,
        int $width = 800,
        int $height = 600
    ): string {
        $keywords = self::keywordsFor($categoryName);
        $keyword = self::pickUniqueKeyword($categoryName, $keywords);

        return self::buildLoremFlickrUrl($keyword, $width, $height);
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalImageUrls(
        string $categoryName,
        int $count = 3,
        int $width = 800,
        int $height = 600
    ): array {
        $images = [];
        for ($i = 0; $i < $count; $i++) {
            $images[] = self::getImageUrl($categoryName, $width, $height);
        }

        return $images;
    }

    public static function getImageUrlFromTitle(
        string $title,
        string $categoryName,
        int $width = 800,
        int $height = 600
    ): string {
        $titleKeywordMap = [
            'yoga' => 'yoga,wellness,meditation',
            'marathon' => 'marathon,running,race',
            'comedy' => 'comedy,stage,microphone',
            'jazz' => 'jazz,music,saxophone',
            'salsa' => 'salsa,dance,latin',
            'food' => 'food,restaurant,delicious',
            'wine' => 'wine,tasting,elegant',
            'beer' => 'beer,festival,crowd',
            'art' => 'art,gallery,creative',
            'photo' => 'photography,camera,art',
            'film' => 'cinema,film,movie',
            'startup' => 'startup,tech,innovation',
            'tech' => 'technology,modern,digital',
            'dance' => 'dance,performance,stage',
            'festival' => 'festival,outdoor,crowd',
            'market' => 'market,stalls,shopping',
            'christmas' => 'christmas,winter,celebration',
            'halloween' => 'halloween,spooky,night',
            'networking' => 'networking,business,people',
            'workshop' => 'workshop,creative,hands',
            'concert' => 'concert,music,crowd',
            'conference' => 'conference,speaker,business',
            'screening' => 'cinema,screening,audience',
            'exhibition' => 'exhibition,gallery,art',
            'masterclass' => 'class,learning,workshop',
            'breakfast' => 'breakfast,morning,food',
            'dinner' => 'dinner,restaurant,elegant',
            'rooftop' => 'rooftop,city,view',
            'outdoor' => 'outdoor,nature,event',
            'kids' => 'children,fun,kids',
            'family' => 'family,together,happy',
            'charity' => 'charity,community,people',
            'run' => 'running,race,sports',
            'basketball' => 'basketball,court,sports',
            'football' => 'football,stadium,sports',
            'open mic' => 'microphone,stage,performance',
            'rave' => 'rave,neon,nightclub',
            'club' => 'nightclub,dj,lights',
        ];

        $titleLower = strtolower($title);
        foreach ($titleKeywordMap as $titleWord => $imageKeyword) {
            if (str_contains($titleLower, $titleWord)) {
                return self::buildLoremFlickrUrl($imageKeyword, $width, $height);
            }
        }

        return self::getImageUrl($categoryName, $width, $height);
    }

    public static function resetImageRegistry(): void
    {
        self::$usedImages = [];
    }

    /**
     * Deterministic Picsum image (not tag-themed); useful as a last-resort seed.
     */
    public static function buildPicsumSeedUrl(string $seed, int $width = 800, int $height = 600): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9\-]/', '-', $seed);

        return "https://picsum.photos/seed/{$safe}/{$width}/{$height}";
    }

    /**
     * @param  list<string>  $keywords
     */
    private static function pickUniqueKeyword(string $category, array $keywords): string
    {
        $shuffled = $keywords;
        shuffle($shuffled);

        foreach ($shuffled as $keyword) {
            $key = $category.':'.$keyword;
            if (! in_array($key, self::$usedImages, true)) {
                self::$usedImages[] = $key;
                if (count(self::$usedImages) > 500) {
                    array_shift(self::$usedImages);
                }

                return $keyword;
            }
        }

        return $keywords[array_rand($keywords)];
    }

    /**
     * @return list<string>
     */
    private static function keywordsFor(string $categoryName): array
    {
        $name = trim($categoryName);
        if (isset(self::$categoryKeywords[$name])) {
            return self::$categoryKeywords[$name];
        }

        foreach (self::$categoryKeywords as $key => $pool) {
            if (strcasecmp($key, $name) === 0) {
                return $pool;
            }
        }

        return self::$defaultKeywords;
    }

    private static function buildLoremFlickrUrl(string $keyword, int $width, int $height): string
    {
        $tags = preg_replace('/\s*,\s*/', ',', trim($keyword));
        $lock = mt_rand(1, 9_999_999);

        return "https://loremflickr.com/{$width}/{$height}/{$tags}?lock={$lock}";
    }
}
