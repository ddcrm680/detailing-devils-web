<?php

namespace App\Contracts\Seo;

use App\Services\Seo\ContentMetadata;

interface ContentMetadataProvider
{
    /** @return iterable<ContentMetadata> Actual content records, never guessed route parameters. */
    public function pages(): iterable;
}
