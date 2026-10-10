<?php

namespace App\Services\Franchise;

use App\Contracts\Franchise\SmsDelivery;
use InvalidArgumentException;
use Throwable;

class OtpDeliveryService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly SmsDelivery $sms,
        private readonly PhoneNumberNormalizer $phones,
    ) {}

    /** Return true only when the SMS provider accepts the code for delivery. */
    public function send(string $purpose, string $mobile, string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Applicant name is required for the OTP message.');
        }

        $normalized = $this->phones->normalizeMobile($mobile)
            ?? throw new InvalidArgumentException('Invalid international mobile number.');
        $challenge = $this->otp->createChallenge($purpose, $normalized);
        $message = "Hello {$name}, {$challenge->code} is your OTP for Signup at Detailing Devils India Pvt Ltd.";

        try {
            $accepted = $this->sms->send($normalized, $message);
        } catch (Throwable $exception) {
            $this->otp->cancelChallenge($purpose, $normalized, $challenge->id);

            throw $exception;
        }

        if (! $accepted) {
            $this->otp->cancelChallenge($purpose, $normalized, $challenge->id);
        }

        return $accepted;
    }
}
