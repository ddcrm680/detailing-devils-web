<?php

namespace App\Services\Franchise;

use RuntimeException;

class OtpException extends RuntimeException
{
    public const COOLDOWN = 'cooldown';

    public const SEND_LIMIT = 'send_limit';

    public const BUSY = 'busy';

    public function __construct(
        public readonly string $reason,
        public readonly int $retryAfter = 0,
    ) {
        parent::__construct($reason);
    }
}
