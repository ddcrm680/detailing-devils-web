<?php

namespace App\Services\Seo\Admin;

use App\Contracts\Seo\MetadataRepository;
use App\Services\Seo\SeoDiagnostics;
use App\Services\Seo\SeoMetadata;
use App\Services\Seo\SeoOverride;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSitemap;
use App\Services\Seo\SeoSnapshot;
use App\Services\Seo\SeoUrl;

final class SeoAdminPages
{
    public function base(): SeoResolver
    {
        $snapshot = app(MetadataRepository::class)->snapshot();

        return $this->resolver(new SeoSnapshot(content: $snapshot->content, images: $snapshot->images));
    }

    public function resolver(SeoSnapshot $snapshot): SeoResolver
    {
        return new SeoResolver(
            new class($snapshot) implements MetadataRepository
            {
                public function __construct(private SeoSnapshot $snapshot) {}

                public function snapshot(): SeoSnapshot
                {
                    return $this->snapshot;
                }
            },
            new SeoUrl
        );
    }

    public function page(string $id, ?SeoSnapshot $snapshot = null): SeoMetadata
    {
        foreach (($snapshot ? $this->resolver($snapshot) : app(SeoResolver::class))->inventory() as $page) {
            if ($page->id === $id) {
                return $page;
            }
        }
        abort(404);
    }

    public function fields(SeoMetadata $page): array
    {
        return [
            'title' => $page->title,
            'description' => $page->description,
            'og_title' => $page->ogTitle,
            'og_description' => $page->ogDescription,
            'og_type' => $page->ogType,
            'twitter_title' => $page->twitterTitle,
            'twitter_description' => $page->twitterDescription,
            'image' => $page->image,
            'canonical' => $page->canonical,
            'robots' => $page->robots,
            'sitemap' => $page->sitemap && $page->indexable(),
        ];
    }

    public function canonicals(string $id): array
    {
        return array_values(array_filter(
            $this->base()->inventory(),
            fn ($page) => $page->published || $page->id === $id
        ));
    }

    public function candidate(SeoSnapshot $snapshot, string $id, array $fields): SeoSnapshot
    {
        $base = $this->page($id, new SeoSnapshot(content: $snapshot->content, images: $snapshot->images));
        // An unchanged self-canonical follows future route/slug changes; aliases stay explicit.
        $overrideFields = $fields;
        if ($fields['canonical'] === $base->canonical) {
            unset($overrideFields['canonical']);
        }
        $overrides = array_values(array_filter(
            $snapshot->overrides,
            fn ($item) => $item->pageId !== $id || $item->locale !== 'en'
        ));
        $overrides[] = new SeoOverride($id, $overrideFields, true, true);

        return new SeoSnapshot($overrides, $snapshot->content, $snapshot->images);
    }

    public function policyChanges(array $fields, SeoMetadata $current): bool
    {
        return $fields['canonical'] !== $current->canonical
            || $fields['robots'] !== $current->robots
            || $fields['sitemap'] !== ($current->sitemap && $current->indexable());
    }

    public function warnings(SeoSnapshot $snapshot): array
    {
        return app(SeoDiagnostics::class)->inspect($this->resolver($snapshot)->inventory());
    }

    public function sitemap(SeoSnapshot $snapshot): string
    {
        return (new SeoSitemap($this->resolver($snapshot)))->xml();
    }
}
