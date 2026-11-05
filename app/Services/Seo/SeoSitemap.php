<?php

namespace App\Services\Seo;

use DOMDocument;
use DOMXPath;
use RuntimeException;

final class SeoSitemap
{
    public function __construct(private SeoResolver $resolver) {}

    /** @return list<SeoMetadata> */
    public function pages(): array
    {
        $seen = [];
        $result = [];
        foreach ($this->resolver->inventory() as $page) {
            if (! $page->sitemap || ! $page->indexable() || $page->canonicalAliasApproved) {
                continue;
            }
            if (isset($seen[$page->canonical])) {
                throw new RuntimeException('Duplicate canonical in sitemap registry.');
            }
            $seen[$page->canonical] = true;
            $result[] = $page;
        }

        if (count($result) > 50000) {
            throw new RuntimeException('A sitemap index is required above 50,000 URLs.');
        }

        return $result;
    }

    public function xml(): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
        $document->appendChild($root);
        foreach ($this->pages() as $page) {
            $url = $root->appendChild($document->createElement('url'));
            foreach ([
                'loc' => $page->canonical,
                'lastmod' => $page->modifiedAt?->format(DATE_ATOM),
            ] as $name => $value) {
                if ($value !== null) {
                    $node = $document->createElement($name);
                    $node->appendChild($document->createTextNode($value));
                    $url->appendChild($node);
                }
            }
        }

        return $document->saveXML();
    }

    /** Strictly read XML without fetching schemas/entities. */
    public function urls(string $xml): array
    {
        $document = new DOMDocument;
        if (stripos($xml, '<!DOCTYPE') !== false || ! @$document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Invalid sitemap XML.');
        }
        if (
            $document->documentElement->localName !== 'urlset'
            || $document->documentElement->namespaceURI !== 'http://www.sitemaps.org/schemas/sitemap/0.9'
        ) {
            throw new RuntimeException('Invalid sitemap namespace/root.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urls = [];
        foreach ($xpath->query('/s:urlset/s:url') as $node) {
            $locations = $xpath->query('s:loc', $node);
            if ($locations->length !== 1) {
                throw new RuntimeException('Each sitemap entry requires exactly one URL.');
            }
            $url = $locations->item(0)->textContent;
            if ((new SeoUrl)->canonical($url) !== $url || in_array($url, $urls, true)) {
                throw new RuntimeException('Invalid or duplicate sitemap URL.');
            }
            $urls[] = $url;
        }

        sort($urls, SORT_STRING);

        return $urls;
    }
}
