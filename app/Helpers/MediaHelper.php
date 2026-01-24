<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class MediaHelper
{
    /**
     * Generate absolute URL for a media file.
     *
     * @param string $path
     * @return string
     */
    public static function url(string $path): string
    {
        return url(Storage::disk('public')->url($path));
    }
}
