<?php

namespace App\Services\Seo;

use InvalidArgumentException;

final class SeoUrl
{
    public function canonical(string $path): string
    {
        $origin = rtrim((string) config('seo.origin'), '/');
        if (
            ! filter_var($origin, FILTER_VALIDATE_URL) || parse_url($origin, PHP_URL_SCHEME) !== 'https'
            || parse_url($origin, PHP_URL_PATH)
            || parse_url($origin, PHP_URL_USER)
            || parse_url($origin, PHP_URL_QUERY)
        ) {
            throw new InvalidArgumentException('Invalid trusted SEO origin.');
        }
        if (str_starts_with($path, $origin.'/')) {
            $path = substr($path, strlen($origin));
        }
        $decoded = rawurldecode($path);
        if (
            preg_match('/[\x00-\x20<>"#]/', $decoded) || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || preg_match('/[\\\\\x00-\x20<>"#]/', $path)
            || str_contains(rawurldecode($path), '..')
            || str_contains(rawurldecode($path), '\\')
        ) {
            throw new InvalidArgumentException('Canonical must use a safe production-relative URL.');
        }

        return $origin.$path;
    }

    public function imageSyntax(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $absolute = $this->canonical($url);
        if (
            parse_url($absolute, PHP_URL_QUERY)
            || ! preg_match('/\.(?:png|jpe?g|webp|avif)$/i', parse_url($absolute, PHP_URL_PATH))
        ) {
            throw new InvalidArgumentException('Sharing image requires a safe production raster URL.');
        }

        return $absolute;
    }

    /** @param list<ApprovedSeoImage> $approved */
    public function image(?string $url, array $approved = []): ?string
    {
        $absolute = $this->imageSyntax($url);
        if ($absolute === null) {
            return null;
        }
        $path = parse_url($absolute, PHP_URL_PATH);
        foreach ($approved as $image) {
            if ($image instanceof ApprovedSeoImage && $image->path === $path) {
                // Already verified/re-encoded by the authorized writer. No reader DB/HTTP lookup.
                return $absolute;
            }
        }
        $file = public_path(ltrim(rawurldecode($path), '/'));
        if (! is_file($file) || @getimagesize($file) === false) {
            throw new InvalidArgumentException('Sharing image must be an existing or writer-approved raster asset.');
        }

        return $absolute;
    }
}
