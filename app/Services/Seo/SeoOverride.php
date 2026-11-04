<?php

namespace App\Services\Seo;

use InvalidArgumentException;

final readonly class SeoOverride
{
    /**
     * Repository adapters must set policyApproved only after server-side publishing authorization.
     * It is never an input flag accepted from a browser or an editable metadata field.
     *
     * @param  array<string, string|bool|null>  $fields
     */
    public function __construct(
        public string $pageId,
        public array $fields,
        public bool $published = false,
        public bool $policyApproved = false,
        public string $locale = 'en',
    ) {
        $allowed = [
            'title',
            'description',
            'og_title',
            'og_description',
            'og_type',
            'twitter_title',
            'twitter_description',
            'image',
            'canonical',
            'robots',
            'sitemap',
        ];
        foreach ($fields as $key => $value) {
            if (
                ! in_array($key, $allowed, true)
                || (! is_string($value) && ! is_bool($value) && $value !== null)
            ) {
                throw new InvalidArgumentException('Unsupported SEO override field.');
            }
            if (in_array($key, ['canonical', 'robots', 'sitemap'], true) && ! $policyApproved) {
                throw new InvalidArgumentException('SEO URL/indexing changes require policy approval.');
            }
            if (
                $value === null
                && in_array($key, ['title', 'description', 'canonical', 'robots', 'og_type'], true)
            ) {
                throw new InvalidArgumentException('Required SEO fields cannot be null.');
            }
            if (($key === 'sitemap' && ! is_bool($value)) || ($key !== 'sitemap' && is_bool($value))) {
                throw new InvalidArgumentException('Invalid SEO override value type.');
            }
        }
        if (
            ! preg_match('/^[A-Za-z0-9_.:-]{1,200}$/', $pageId)
            || ! preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale)
        ) {
            throw new InvalidArgumentException('Invalid SEO publication identity.');
        }
    }
}
