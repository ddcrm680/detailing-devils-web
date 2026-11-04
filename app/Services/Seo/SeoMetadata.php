<?php

namespace App\Services\Seo;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SeoMetadata
{
    /** @param list<JsonLd> $structuredData @param list<string> $warnings */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $canonical,
        public string $robots = 'index, follow',
        public string $ogType = 'website',
        public ?string $image = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $twitterTitle = null,
        public ?string $twitterDescription = null,
        public bool $published = true,
        public bool $sitemap = false,
        public string $priority = '0.5000',
        public string $locale = 'en',
        public ?DateTimeImmutable $modifiedAt = null,
        public array $structuredData = [],
        public array $warnings = [],
        public bool $canonicalAliasApproved = false,
    ) {
        foreach ([
            $title,
            $description,
            $ogTitle,
            $ogDescription,
            $twitterTitle,
            $twitterDescription,
        ] as $text) {
            if (
                $text !== null
                && (mb_strlen($text) > 2000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text))
            ) {
                throw new InvalidArgumentException('Invalid SEO text.');
            }
        }
        if (
            ! preg_match('/^[A-Za-z0-9_.:-]{1,200}$/', $id)
            || ! preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale)
            || trim($title) === ''
            || ! in_array(
                $robots,
                ['index, follow', 'noindex, follow', 'noindex, nofollow', 'index, nofollow'],
                true
            )
            || ! in_array($ogType, ['website', 'article'], true)
            || ! preg_match('/^0\.\d{4}$|^1\.0000$/', $priority)
        ) {
            throw new InvalidArgumentException('Invalid SEO metadata contract.');
        }
        $urls = new SeoUrl;
        if ($urls->canonical($canonical) !== $canonical || $urls->imageSyntax($image) !== $image) {
            throw new InvalidArgumentException('Metadata requires absolute production URLs.');
        }
        foreach ($structuredData as $node) {
            if (! $node instanceof JsonLd) {
                throw new InvalidArgumentException('Invalid structured data node.');
            }
        }
    }

    public function indexable(): bool
    {
        return $this->published && ! str_contains($this->robots, 'noindex');
    }
}
