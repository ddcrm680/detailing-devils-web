<?php

namespace App\Services\Seo\Admin;

use App\Services\Seo\ApprovedSeoImage;
use App\Services\Seo\SeoOverride;
use App\Services\Seo\SeoSnapshot;
use InvalidArgumentException;

final class SeoPublicationCodec
{
    public function decode(string $json): SeoSnapshot
    {
        if (strlen($json) > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('SEO snapshot exceeds its safety budget.');
        }
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (
            ! is_array($data) || ($data['format'] ?? null) !== 1 || ! is_array($data['pages'] ?? null)
            || ! is_array($data['images'] ?? null)
        ) {
            throw new InvalidArgumentException('Unsupported SEO publication format.');
        }
        $overrides = [];
        foreach ($data['pages'] as $page) {
            $overrides[] = new SeoOverride($page['id'], $page['fields'], true, true, $page['locale']);
        }
        $images = [];
        foreach ($data['images'] as $image) {
            $images[] = new ApprovedSeoImage(
                $image['path'],
                $image['sha256'],
                $image['width'],
                $image['height'],
                $image['label'] ?? ''
            );
        }

        return new SeoSnapshot(overrides: $overrides, images: $images);
    }

    public function encode(SeoSnapshot $snapshot): string
    {
        return json_encode(
            [
                'format' => 1,
                'pages' => array_map(
                    fn ($override) => [
                        'id' => $override->pageId,
                        'locale' => $override->locale,
                        'fields' => $override->fields,
                    ],
                    $snapshot->overrides
                ),
                'images' => array_map(
                    fn ($image) => [
                        'path' => $image->path,
                        'sha256' => $image->sha256,
                        'width' => $image->width,
                        'height' => $image->height,
                        'label' => $image->label,
                    ],
                    $snapshot->images
                ),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
    }
}
