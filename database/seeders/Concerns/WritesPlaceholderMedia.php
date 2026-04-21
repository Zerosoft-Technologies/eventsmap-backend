<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

trait WritesPlaceholderMedia
{
    /**
     * Tiny valid JPEG (grey-ish 1×1 from picsum) used when GD has no JPEG support and HTTP fails.
     */
    private const PLACEHOLDER_JPEG_BASE64 = '/9j/4QDeRXhpZgAASUkqAAgAAAAGABIBAwABAAAAAQAAABoBBQABAAAAVgAAABsBBQABAAAAXgAAACgBAwABAAAAAgAAABMCAwABAAAAAQAAAGmHBAABAAAAZgAAAAAAAABIAAAAAQAAAEgAAAABAAAABwAAkAcABAAAADAyMTABkQcABAAAAAECAwCGkgcAFgAAAMAAAAAAoAcABAAAADAxMDABoAMAAQAAAP//AAACoAQAAQAAAAEAAAADoAQAAQAAAAEAAAAAAAAAQVNDSUkAAABQaWNzdW0gSUQ6IDgyN//bAEMACAYGBwYFCAcHBwkJCAoMFA0MCwsMGRITDxQdGh8eHRocHCAkLicgIiwjHBwoNyksMDE0NDQfJzk9ODI8LjM0Mv/bAEMBCQkJDAsMGA0NGDIhHCEyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMv/CABEIAAEAAQMBIgACEQEDEQH/xAAVAAEBAAAAAAAAAAAAAAAAAAAABf/EABUBAQEAAAAAAAAAAAAAAAAAAAIE/9oADAMBAAIQAxAAAAGCKh//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAn//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/An//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IX//2gAMAwEAAgADAAAAEAf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q==';

    /**
     * Ensure grey placeholder JPEGs exist under the public disk for seeded image_path values.
     */
    protected function ensureV2PlaceholderImages(): void
    {
        $disk = Storage::disk('public');

        $dirs = [
            'organisers/images',
            'organisers/gallery',
            'talents/images',
            'talents/gallery',
            'venues/images',
            'venues/gallery',
            'events/images',
            'events/gallery',
        ];

        foreach ($dirs as $dir) {
            $disk->makeDirectory($dir);
        }

        $jpeg = $this->greyJpeg800x600();
        if ($jpeg === null || $jpeg === '') {
            $this->command?->warn('Placeholder images skipped (unexpected empty binary).');

            return;
        }

        foreach ([
            'organisers/images/placeholder.jpg',
            'talents/images/placeholder.jpg',
            'venues/images/placeholder.jpg',
            'events/images/placeholder.jpg',
        ] as $path) {
            if (! $disk->exists($path)) {
                $disk->put($path, $jpeg);
            }
        }

        foreach (range(1, 8) as $i) {
            foreach (['organisers/gallery', 'talents/gallery', 'venues/gallery', 'events/gallery'] as $base) {
                $p = "{$base}/{$i}.jpg";
                if (! $disk->exists($p)) {
                    $disk->put($p, $jpeg);
                }
            }
        }
    }

    protected function ensureStorageLink(): void
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        if (File::exists($link)) {
            return;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            try {
                File::copyDirectory($target, $link);
            } catch (\Throwable) {
            }

            return;
        }

        try {
            \symlink($target, $link);
        } catch (\Throwable) {
            try {
                File::copyDirectory($target, $link);
            } catch (\Throwable) {
            }
        }
    }

    private function greyJpeg800x600(): ?string
    {
        // Some PHP builds load ext-gd without JPEG support: imagecreatetruecolor exists but imagejpeg does not.
        if (\extension_loaded('gd') && \function_exists('imagecreatetruecolor') && \function_exists('imagejpeg')) {
            try {
                $w = 800;
                $h = 600;
                $im = \imagecreatetruecolor($w, $h);
                if ($im === false) {
                    throw new \RuntimeException('imagecreatetruecolor failed');
                }
                $grey = \imagecolorallocate($im, 200, 200, 200);
                \imagefilledrectangle($im, 0, 0, $w, $h, $grey);
                ob_start();
                \imagejpeg($im, null, 85);
                \imagedestroy($im);
                $bin = ob_get_clean();

                if ($bin !== false && $bin !== '' && str_starts_with($bin, "\xFF\xD8")) {
                    return $bin;
                }
            } catch (\Throwable) {
                // Fall through to HTTP / embedded JPEG.
            }
        }

        try {
            $body = Http::timeout(8)->get('https://picsum.photos/800/600')->body();
            if ($body !== '' && str_starts_with($body, "\xFF\xD8")) {
                return $body;
            }
        } catch (\Throwable) {
            // Use embedded fallback.
        }

        $embedded = \base64_decode(self::PLACEHOLDER_JPEG_BASE64, true);

        return ($embedded !== false && $embedded !== '' && str_starts_with($embedded, "\xFF\xD8"))
            ? $embedded
            : null;
    }
}
