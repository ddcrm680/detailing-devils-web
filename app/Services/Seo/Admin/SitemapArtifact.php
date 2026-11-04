<?php

namespace App\Services\Seo\Admin;

use App\Services\Seo\SeoSitemap;
use RuntimeException;

class SitemapArtifact
{
    public function __construct(private ?string $path = null) {}

    public function read(): string
    {
        $path = $this->path ?? config('seo-admin.sitemap_path');
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The authoritative sitemap artifact is unavailable.');
        }

        return file_get_contents($path);
    }

    public function replace(string $xml): void
    {
        app(SeoSitemap::class)->urls($xml);
        $path = $this->path ?? config('seo-admin.sitemap_path');
        $temporary = tempnam(dirname($path), '.seo-publish-');
        try {
            if (
                ! $temporary || file_put_contents($temporary, $xml) !== strlen($xml)
                || ! chmod($temporary, 0644)
                || ! rename($temporary, $path)
            ) {
                throw new RuntimeException('Unable to atomically replace the authoritative sitemap.');
            }
        } finally {
            if ($temporary && is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
