<?php

namespace App\Support;

use App\Helpers\MediaHelper;
use App\Models\GalleryImage;
use App\Models\User;

/**
 * Resolves Talent / Organiser / Venue V2 main image: profile {@see $profile->image_path} first, then owner's {@see User::$profile_image_path}.
 */
final class V2ProfileCoverImage
{
    private static function isNonEmptyString(?string $value): bool
    {
        return is_string($value) && $value !== '';
    }

    public static function resolveOwner(object $profile, ?User $explicitOwner = null): ?User
    {
        if ($explicitOwner instanceof User) {
            return $explicitOwner;
        }

        $u = $profile->user ?? null;

        return $u instanceof User ? $u : null;
    }

    /**
     * Stored value shown as main image path/id/url: profile cover first, else user's avatar field.
     */
    public static function effectiveStoredPathForProfile(object $profile, ?User $owner = null): ?string
    {
        $owner = self::resolveOwner($profile, $owner);
        $profilePath = $profile->image_path ?? null;

        if (self::isNonEmptyString($profilePath)) {
            return $profilePath;
        }

        if ($owner === null) {
            return null;
        }

        $fallback = $owner->profile_image_path ?? null;

        return self::isNonEmptyString($fallback) ? $fallback : null;
    }

    /**
     * Absolute URL for display (gallery UUID, disk path, or external URL).
     */
    public static function resolveStoredToUrl(?string $stored, int $profileUserId): ?string
    {
        if (! self::isNonEmptyString($stored)) {
            return null;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $stored)) {
            $galleryImage = GalleryImage::query()
                ->where('image_id', $stored)
                ->where('user_id', $profileUserId)
                ->where('is_deleted', false)
                ->first();

            return $galleryImage ? MediaHelper::url($galleryImage->file_path) : null;
        }

        return MediaHelper::resolveUrl($stored);
    }

    /**
     * Resolved cover URL: profile image if present and resolvable; otherwise user's profile photo.
     */
    public static function coverImageUrl(object $profile, ?User $owner = null): ?string
    {
        $owner = self::resolveOwner($profile, $owner);
        $userId = (int) ($profile->user_id ?? 0);
        $profilePath = $profile->image_path ?? null;

        if (self::isNonEmptyString($profilePath)) {
            $url = self::resolveStoredToUrl($profilePath, $userId);
            if ($url !== null && $url !== '') {
                return $url;
            }
        }

        if ($owner === null) {
            return null;
        }

        $fallback = $owner->profile_image_path ?? null;
        if (! self::isNonEmptyString($fallback)) {
            return null;
        }

        return MediaHelper::resolveUrl($fallback);
    }
}
