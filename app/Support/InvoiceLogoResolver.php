<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves company logo to a base64 data URI for DomPDF (local path, absolute path, or URL).
 */
final class InvoiceLogoResolver
{
    public static function dataUri(?string $configuredPath = null): ?string
    {
        $candidates = array_values(array_filter([
            $configuredPath,
            config('invoice.company.logo_path'),
            config('mail.logo_url'),
            'images/marker.png',
            'images/invoice-logo.png',
        ], fn ($v) => is_string($v) && $v !== ''));

        foreach ($candidates as $candidate) {
            $uri = self::resolveCandidate($candidate);
            if ($uri !== null) {
                return $uri;
            }
        }

        return null;
    }

    private static function resolveCandidate(string $path): ?string
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return self::fromUrl($path);
        }

        $normalized = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        $localCandidates = [
            public_path($normalized),
            public_path('images'.DIRECTORY_SEPARATOR.basename($normalized)),
            $path,
        ];

        foreach ($localCandidates as $local) {
            if (is_string($local) && is_file($local) && is_readable($local)) {
                return self::fromFile($local);
            }
        }

        return null;
    }

    private static function fromFile(string $filePath): ?string
    {
        $contents = @file_get_contents($filePath);
        if ($contents === false || $contents === '') {
            return null;
        }

        $mime = mime_content_type($filePath) ?: self::guessMimeFromExtension($filePath);

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private static function fromUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(15)->get($url);
            if ($response->successful()) {
                $body = $response->body();
                if ($body !== '') {
                    $mime = $response->header('Content-Type')
                        ? strtok($response->header('Content-Type'), ';')
                        : self::guessMimeFromExtension($url);

                    return 'data:'.$mime.';base64,'.base64_encode($body);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Invoice logo HTTP fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
        }

        $context = stream_context_create([
            'http' => ['timeout' => 15, 'follow_location' => 1],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $contents = @file_get_contents($url, false, $context);
        if ($contents === false || $contents === '') {
            return null;
        }

        return 'data:'.self::guessMimeFromExtension($url).';base64,'.base64_encode($contents);
    }

    private static function guessMimeFromExtension(string $path): string
    {
        $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }
}
