<?php

namespace App\Services\Seo;

use DateTimeImmutable;
use InvalidArgumentException;

/** Adapter contract for future content records. No model, database or route is created. */
final readonly class ContentMetadata
{
    /** @param array<string, string|int> $routeParameters @param list<JsonLd> $structuredData */
    public function __construct(
        public string $id,
        public string $type,
        public string $routeName,
        public array $routeParameters,
        public string $title,
        public string $description = '',
        public bool $published = false,
        public bool $indexable = true,
        public bool $sitemap = false,
        public string $locale = 'en',
        public ?DateTimeImmutable $modifiedAt = null,
        public ?string $image = null,
        public array $structuredData = [],
    ) {
        if (
            ! preg_match('/^[A-Za-z0-9_.:-]{1,200}$/', $id) || $title === ''
            || ! in_array($type, ['service', 'studio', 'franchise', 'article', 'location'], true)
            || ! preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale)
        ) {
            throw new InvalidArgumentException('Invalid SEO content contract.');
        }
        foreach ($routeParameters as $key => $value) {
            if (! is_string($key) || (! is_string($value) && ! is_int($value))) {
                throw new InvalidArgumentException('Invalid content route parameter.');
            }
        }
        foreach ($structuredData as $node) {
            if (! $node instanceof JsonLd) {
                throw new InvalidArgumentException('Structured data must use typed factories.');
            }
        }
    }
}
