<?php

namespace App\Services\Seo;

use App\Contracts\Seo\ContentMetadataProvider;
use App\Contracts\Seo\MetadataRepository;
use App\Services\Seo\Admin\SeoPublicationCodec;
use App\Services\Seo\Admin\SeoStorage;

final class DatabaseMetadataRepository implements MetadataRepository
{
    public function __construct(private SeoStorage $storage, private SeoPublicationCodec $codec) {}

    public function snapshot(): SeoSnapshot
    {
        // One atomic snapshot per resolver/request; no independent per-host metadata cache.
        $snapshot = $this->codec->decode($this->storage->state()->payload);
        $content = $snapshot->content;
        foreach (config('seo.content_providers', []) as $class) {
            $provider = app($class);
            if (! $provider instanceof ContentMetadataProvider) {
                throw new \RuntimeException('Invalid SEO content provider.');
            }
            foreach ($provider->pages() as $record) {
                $content[] = $record;
            }
        }

        return new SeoSnapshot($snapshot->overrides, $content, $snapshot->images);
    }
}
