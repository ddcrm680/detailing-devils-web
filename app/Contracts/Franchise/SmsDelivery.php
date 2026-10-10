<?php

namespace App\Contracts\Franchise;

interface SmsDelivery
{
    /** Return true only when the provider accepts the message for delivery. */
    public function send(string $mobile, string $message): bool;
}
