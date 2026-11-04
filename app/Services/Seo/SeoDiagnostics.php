<?php

namespace App\Services\Seo;

use DOMDocument;
use DOMXPath;

final class SeoDiagnostics
{
    /** @param list<SeoMetadata> $pages @return list<array{level: string, page: string, message: string}> */
    public function inspect(array $pages): array
    {
        $issues = [];
        $groups = [];
        $canonicals = [];
        foreach ($pages as $page) {
            foreach ($page->warnings as $warning) {
                $issues[] = [
                    'level' => 'warning',
                    'page' => $page->id,
                    'message' => $warning,
                ];
            }
            foreach (['title', 'description'] as $field) {
                $value = $page->$field;
                if (trim($value) === '') {
                    $issues[] = [
                        'level' => 'error',
                        'page' => $page->id,
                        'message' => 'Missing '.$field.'.',
                    ];
                }
                if (strip_tags($value) !== $value) {
                    $issues[] = [
                        'level' => 'warning',
                        'page' => $page->id,
                        'message' => $field.' contains markup-like text; output is escaped.',
                    ];
                }
                if (mb_strlen($value) > ($field === 'title' ? 65 : 170)) {
                    $issues[] = [
                        'level' => 'warning',
                        'page' => $page->id,
                        'message' => 'Long '.$field.' preserved; editorial review recommended.',
                    ];
                }
                $groups[$field][$value][] = $page->id;
            }
            if ($page->image === null) {
                $issues[] = [
                    'level' => 'warning',
                    'page' => $page->id,
                    'message' => 'No approved social-sharing image.',
                ];
            }
            $canonicals[$page->canonical][] = $page;
            if ($page->sitemap && ! $page->indexable()) {
                $issues[] = [
                    'level' => 'warning',
                    'page' => $page->id,
                    'message' => 'Unpublished/noindex content excluded from sitemap.',
                ];
            }
            foreach ($page->structuredData as $node) {
                $data = json_decode($node->json(), true, flags: JSON_THROW_ON_ERROR);
                if (($data['@context'] ?? '') !== 'https://schema.org' || empty($data['@type'])) {
                    $issues[] = [
                        'level' => 'error',
                        'page' => $page->id,
                        'message' => 'Invalid structured data.',
                    ];
                }
            }
        }
        foreach ($canonicals as $canonical => $group) {
            $targets = array_filter($group, fn ($page) => ! $page->canonicalAliasApproved && $page->indexable());
            $aliases = array_filter($group, fn ($page) => $page->canonicalAliasApproved && ! $page->sitemap);
            if (count($group) > 1 && (count($targets) !== 1 || count($aliases) !== count($group) - 1)) {
                $issues[] = [
                    'level' => 'error',
                    'page' => implode(', ', array_map(fn ($page) => $page->id, $group)),
                    'message' => 'Conflicting canonical targets.',
                ];
            } elseif ($aliases && count($targets) !== 1) {
                $issues[] = [
                    'level' => 'error',
                    'page' => implode(', ', array_map(fn ($page) => $page->id, $aliases)),
                    'message' => 'Canonical alias has no indexable self-canonical target.',
                ];
            } elseif ($aliases) {
                $issues[] = [
                    'level' => 'info',
                    'page' => implode(', ', array_map(fn ($page) => $page->id, $aliases)),
                    'message' => 'Explicitly approved canonical alias; excluded from sitemap.',
                ];
            }
        }
        foreach ($groups as $field => $values) {
            foreach ($values as $ids) {
                if (count($ids) < 2) {
                    continue;
                }
                $intentional = false;
                foreach (config('seo.intentional_duplicates.'.$field, []) as $allowed) {
                    $intentional = $intentional || array_diff($ids, $allowed) === [];
                }
                $issues[] = [
                    'level' => $intentional ? 'info' : 'warning',
                    'page' => implode(', ', $ids),
                    'message' => ($intentional ? 'Intentional shared ' : 'Duplicate ').$field.'.',
                ];
            }
        }

        return $issues;
    }

    /** Inspect a saved, rendered document. Never executes routes or makes network requests. */
    public function html(string $html, SeoMetadata $page): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $issues = [];
        $expected = [
            '//title' => $page->title,
            '//meta[@name="description"]/@content' => $page->description,
            '//link[@rel="canonical"]/@href' => $page->canonical,
            '//meta[@name="robots"]/@content' => $page->robots,
            '//meta[@property="og:title"]/@content' => $page->ogTitle ?? $page->title,
            '//meta[@property="og:description"]/@content' => $page->ogDescription ?? $page->description,
            '//meta[@property="og:type"]/@content' => $page->ogType,
            '//meta[@property="og:url"]/@content' => $page->canonical,
            '//meta[@name="twitter:card"]/@content' => $page->image ? 'summary_large_image' : 'summary',
            '//meta[@name="twitter:title"]/@content' => $page->twitterTitle ?? $page->ogTitle ?? $page->title,
            '//meta[@name="twitter:description"]/@content' => $page->twitterDescription
                    ?? $page->ogDescription
                    ?? $page->description,
        ];
        if (config('site.google_site_verification')) {
            $expected['//meta[@name="google-site-verification"]/@content'] = config('site.google_site_verification');
        }
        if ($page->image) {
            $expected['//meta[@property="og:image"]/@content'] = $page->image;
            $expected['//meta[@name="twitter:image"]/@content'] = $page->image;
        } elseif ($xpath->query('//meta[@property="og:image"]|//meta[@name="twitter:image"]')->length) {
            $issues[] = 'Unexpected social image.';
        }
        foreach ($expected as $query => $value) {
            $nodes = $xpath->query($query);
            $headNodes = $xpath->query(str_replace('//', '/html/head/', $query));
            if ($nodes->length !== 1 || $headNodes->length !== 1 || $nodes->item(0)->textContent !== $value) {
                $issues[] = 'Missing, duplicate, misplaced or conflicting metadata: '.$query;
            }
        }
        $nodes = $xpath->query('//script[@type="application/ld+json"]');
        if ($nodes->length !== count($page->structuredData)) {
            $issues[] = 'Structured data count mismatch.';
        }
        foreach ($nodes as $i => $node) {
            try {
                $data = json_decode($node->textContent, true, flags: JSON_THROW_ON_ERROR);
                if (! isset($page->structuredData[$i]) || $data !== $page->structuredData[$i]->data) {
                    $issues[] = 'Structured data mismatch.';
                }
            } catch (\JsonException) {
                $issues[] = 'Invalid structured data JSON.';
            }
        }

        return $issues;
    }
}
