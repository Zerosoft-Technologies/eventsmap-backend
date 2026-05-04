<?php

namespace App\Support;

/**
 * Simple draft vs published visibility for events and V2 profiles.
 */
final class PublishStatus
{
    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const ALL = [
        self::DRAFT,
        self::PUBLISHED,
    ];

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
        ];
    }
}
