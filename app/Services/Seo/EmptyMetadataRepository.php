<?php

namespace App\Services\Seo;

use App\Contracts\Seo\MetadataRepository;

/** Safe default until an authorized website-owned publication store is available. */
final class EmptyMetadataRepository implements MetadataRepository
{
    public function snapshot(): SeoSnapshot
    {
        return new SeoSnapshot;
    }
}
