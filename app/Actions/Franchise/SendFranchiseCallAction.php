<?php

namespace App\Actions\Franchise;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

class SendFranchiseCallAction
{
    public function __invoke(string $model, string $mobile): void
    {
        $label = match ($model) {
            'fofo' => 'FOFO',
            'xclusive' => 'X-Clusive',
            default => throw new LogicException('Unsupported franchise calling model.'),
        };

        if (! config("services.{$model}.call_enabled")) {
            return;
        }

        $url = config('services.franchise_call.url');
        if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || ! parse_url($url, PHP_URL_HOST)) {
            Log::warning("{$label} calling callback skipped: HTTPS endpoint is not configured.");

            return;
        }

        // Controllers pass validated E.164; the provider expects 0 + ten Indian digits.
        if (! preg_match('/\A\+91([0-9]{10})\z/', $mobile, $matches)) {
            Log::warning("{$label} calling callback skipped: the mobile number is outside the legacy format.");

            return;
        }

        try {
            $response = Http::connectTimeout(2)->timeout(4)->get($url, ['cust_mob' => '0'.$matches[1]]);
            if (! $response->successful()) {
                Log::warning("{$label} calling callback failed.", ['status' => $response->status()]);
            }
        } catch (Throwable $exception) {
            Log::warning("{$label} calling callback failed.", ['exception' => $exception::class]);
        }
    }
}
