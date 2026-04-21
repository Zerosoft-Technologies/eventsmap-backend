<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class MediaHelper
{
    /**
     * Generate absolute URL for a media file.
     */
    public static function url(string $path): string
    {
        return url(Storage::disk('public')->url($path));
    }

    /**
     * Resolve a stored image value to a usable URL (local public disk path or already-absolute URL).
     */
    public static function resolveUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return self::url($path);
    }
}
