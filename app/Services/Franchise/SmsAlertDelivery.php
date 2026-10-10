<?php

namespace App\Services\Franchise;

use App\Contracts\Franchise\SmsDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SmsAlertDelivery implements SmsDelivery
{
    private const ENDPOINT = 'https://www.smsalert.co.in/api/push.json';

    public function send(string $mobile, string $message): bool
    {
        $apiKey = config('services.sms_alert.api_key');
        $sender = config('services.sms_alert.sender');
        if (config('services.sms_alert.enabled') !== true
            || ! is_string($apiKey) || trim($apiKey) === ''
            || ! is_string($sender) || trim($sender) === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withoutRedirecting()
                ->connectTimeout(3)
                ->timeout(10)
                ->post(self::ENDPOINT, [
                    'apikey' => $apiKey,
                    'sender' => $sender,
                    'mobileno' => ltrim($mobile, '+'),
                    'text' => $message,
                ]);
        } catch (ConnectionException) {
            return false;
        }

        if ($response->status() === 429) {
            $retryAfter = (int) $response->header('Retry-After');

            throw new OtpException(OtpException::SEND_LIMIT, $retryAfter > 0 ? $retryAfter : 30);
        }

        if (! $response->successful() || $response->json('status') !== 'success') {
            return false;
        }

        $details = $response->json('description.batch_dtl');

        return is_array($details)
            && count($details) === 1
            && in_array(strtoupper((string) ($details[0]['status'] ?? '')), ['AWAITED-DLR', 'DELIVRD'], true);
    }
}
