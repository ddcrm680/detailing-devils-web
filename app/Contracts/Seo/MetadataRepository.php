<?php

namespace App\Contracts\Seo;

use App\Services\Seo\SeoSnapshot;

interface MetadataRepository
{
    /** One consistent publication snapshot, with no draft data exposed to visitors. */
    public function snapshot(): SeoSnapshot;
}
