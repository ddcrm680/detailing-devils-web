<?php

namespace App\Services\Seo;

use InvalidArgumentException;

/** Only facts explicitly supplied by trusted content adapters enter schema factories. */
final readonly class JsonLd
{
    private function __construct(public array $data) {}

    public static function organization(string $name, string $url): self
    {
        return self::entity('Organization', $name, $url);
    }

    public static function website(string $name, string $url): self
    {
        return self::entity('WebSite', $name, $url);
    }

    public static function localBusiness(string $name, string $url): self
    {
        // No invented address, ratings, hours or reviews; an adapter must verify its facts.
        return self::entity('LocalBusiness', $name, $url);
    }

    public static function article(string $headline, string $url, string $description): self
    {
        self::check($headline, $url);

        return new self([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $headline,
            'url' => $url,
            'description' => $description,
        ]);
    }

    /** @param list<array{name: string, url: string}> $items */
    public static function breadcrumbs(array $items): self
    {
        if ($items === []) {
            throw new InvalidArgumentException('Breadcrumbs require verified items.');
        }
        $list = [];
        foreach ($items as $i => $item) {
            self::check($item['name'], $item['url']);
            $list[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return new self([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ]);
    }

    private static function entity(string $type, string $name, string $url): self
    {
        self::check($name, $url);

        return new self([
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $name,
            'url' => $url,
        ]);
    }

    private static function check(string $name, string $url): void
    {
        if (
            trim($name) === '' || ! filter_var($url, FILTER_VALIDATE_URL)
            || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || parse_url($url, PHP_URL_USER)
            || parse_url($url, PHP_URL_PASS)
        ) {
            throw new InvalidArgumentException('Structured data requires a name and safe HTTPS URL.');
        }
    }

    public function json(): string
    {
        return json_encode(
            $this->data,
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );
    }
}
