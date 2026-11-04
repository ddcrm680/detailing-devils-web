<?php

namespace App\Services\Seo;

use InvalidArgumentException;

final readonly class SeoSnapshot
{
    /** @param list<SeoOverride> $overrides @param list<ContentMetadata> $content */
    public function __construct(
        public array $overrides = [],
        public array $content = [],
        public array $images = []
    ) {
        foreach ($images as $image) {
            if (! $image instanceof ApprovedSeoImage) {
                throw new InvalidArgumentException('Invalid SEO image manifest.');
            }
        }
        $keys = [];
        foreach ($overrides as $override) {
            if (! $override instanceof SeoOverride) {
                throw new InvalidArgumentException('Invalid SEO publication snapshot.');
            }
            $key = $override->pageId.':'.$override->locale;
            if (isset($keys[$key])) {
                throw new InvalidArgumentException('Duplicate SEO publication identity.');
            }
            $keys[$key] = true;
        }
        $keys = [];
        foreach ($content as $record) {
            if (! $record instanceof ContentMetadata || isset($keys[$record->id.':'.$record->locale])) {
                throw new InvalidArgumentException('Invalid or duplicate SEO content identity.');
            }
            $keys[$record->id.':'.$record->locale] = true;
        }
    }
}
