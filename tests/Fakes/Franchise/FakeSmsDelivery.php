<?php

namespace Tests\Fakes\Franchise;

use App\Contracts\Franchise\SmsDelivery;
use RuntimeException;

class FakeSmsDelivery implements SmsDelivery
{
    /** @var list<array{mobile: string, message: string}> */
    public array $sent = [];

    public bool $accept = true;

    public bool $throw = false;

    public function send(string $mobile, string $message): bool
    {
        if ($this->throw) {
            throw new RuntimeException('Fake SMS delivery failure.');
        }

        if (! $this->accept) {
            return false;
        }

        $this->sent[] = compact('mobile', 'message');

        return true;
    }
}
