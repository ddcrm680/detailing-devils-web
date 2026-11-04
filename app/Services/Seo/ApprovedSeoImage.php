<?php

namespace App\Services\Seo;

use InvalidArgumentException;

/** Writer-verified immutable media manifest, transported in the shared publication snapshot. */
final readonly class ApprovedSeoImage
{
    public function __construct(
        public string $path,
        public string $sha256,
        public int $width,
        public int $height,
        public string $label = ''
    ) {
        if (
            ! preg_match('~^/uploads/images/seo/[a-f0-9-]{36}\.jpg$~D', $path)
            || ! preg_match('/^[a-f0-9]{64}$/D', $sha256)
            || min($width, $height) < 200
            || max($width, $height) > 2400
            || mb_strlen($label) > 120
            || strip_tags($label) !== $label
            || preg_match('/[\x00-\x1F]/', $label)
        ) {
            throw new InvalidArgumentException('Invalid approved SEO image manifest.');
        }
    }
}
