<?php

namespace App\Services\Franchise;

final readonly class OtpChallenge
{
    public function __construct(
        public string $id,
        public string $code,
    ) {}
}
