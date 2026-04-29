<?php

namespace App\Support;

/**
 * Publication visibility state for premium profiles (Talent / Venue / Organiser V2).
 *
 * New profiles are created as {@see self::DRAFT}; owners later set a published state from the UI.
 */
final class ProfilePublicationStatus
{
    public const DRAFT = 'draft';

    public const UPCOMING = 'upcoming';

    public const COMPLETED = 'completed';

    public const SUSPENDED = 'suspended';

    public const CANCELLED = 'cancelled';

    /** All persisted values (includes draft). */
    public const ALL = [
        self::DRAFT,
        self::UPCOMING,
        self::COMPLETED,
        self::SUSPENDED,
        self::CANCELLED,
    ];

    /** Values shown after creation when picking status from sidebar/dropdown (excludes draft). */
    public const SIDEBAR_OPTIONS = [
        self::UPCOMING,
        self::COMPLETED,
        self::SUSPENDED,
        self::CANCELLED,
    ];

    /**
     * Human-readable labels for API consumers (e.g. frontend dropdowns).
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::DRAFT => 'Draft',
            self::UPCOMING => 'Upcoming',
            self::COMPLETED => 'Completed',
            self::SUSPENDED => 'Suspended',
            self::CANCELLED => 'Cancelled',
        ];
    }
}
