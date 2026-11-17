<?php

namespace App\Services\LeadDelivery;

use App\Models\LeadDelivery;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WebhookDelivery
{
    public function contract(): array
    {
        $config = config('lead_delivery');
        $url = $config['endpoint'];
        $parts = is_string($url) ? parse_url($url) : false;
        if (
            ! $config['contract_confirmed']
            || ! filter_var($url, FILTER_VALIDATE_URL)
            || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || str_contains((string) $url, '\\')
        ) {
            throw new DomainException('Configure and confirm the HTTPS webhook, acceptance and idempotency contract first.');
        }
        foreach (['auth_header', 'reference_header'] as $key) {
            if (
                ! is_string($config[$key])
                || ! preg_match('/\A[A-Za-z][A-Za-z0-9-]{0,63}\z/', $config[$key])
                || in_array(strtolower($config[$key]), [
                    'host',
                    'content-type',
                    'content-length',
                    'accept',
                    'connection',
                    'transfer-encoding',
                ], true)
            ) {
                throw new DomainException('Configure valid webhook authentication and reference headers.');
            }
        }
        if (
            strcasecmp($config['auth_header'], $config['reference_header']) === 0
            || ($config['token'] !== null && ! is_string($config['token']))
            || preg_match('/[\r\n\x00]/', (string) $config['token'])
            || ! is_string($config['auth_scheme'])
            || ! preg_match('/\A[A-Za-z0-9_-]*\z/', $config['auth_scheme'])
        ) {
            throw new DomainException('Configure valid webhook authentication and reference headers.');
        }
        if (
            $config['accepted_statuses'] !== [200, 201]
            || $config['connect_timeout'] < 1
            || $config['timeout'] < $config['connect_timeout']
            || $config['timeout'] > 30
            || $config['max_attempts'] < 1
            || $config['max_attempts'] > 100
            || ! $config['retry_delays']
            || array_filter(
                $config['retry_delays'],
                fn ($delay) => ! is_int($delay) || $delay < 1 || $delay > 604800
            )
            || $config['run_seconds'] < $config['timeout'] + 5
            || $config['run_seconds'] > 50
        ) {
            throw new DomainException('Configure valid webhook acceptance, timeout and retry settings.');
        }

        return $config;
    }

    public function send(LeadDelivery $delivery, array $config): void
    {
        $eligible = LeadDelivery::query()
            ->whereKey($delivery->id)
            ->where('source_record_id', '>', 0)
            ->where('attempts', $delivery->attempts)
            ->whereIn('status', ['pending', 'processing'])
            ->where('next_attempt_at', '<=', now());
        if ($delivery->attempts >= $config['max_attempts']) {
            $eligible->update([
                'status' => 'review',
                'last_error' => 'retry_exhausted',
                'processing_token' => null,
                'next_attempt_at' => null,
            ]);

            return;
        }
        try {
            $payload = $delivery->payload;
            if (! is_array($payload) || ($payload['lead_reference'] ?? null) !== $delivery->reference) {
                throw new RuntimeException;
            }
        } catch (Throwable) {
            $eligible->update([
                'status' => 'review',
                'last_error' => 'payload_unavailable',
                'processing_token' => null,
                'next_attempt_at' => null,
            ]);

            return;
        }

        $token = (string) Str::uuid();
        $claimed = $eligible->update([
            'status' => 'processing',
            'processing_token' => $token,
            'attempts' => $delivery->attempts + 1,
            'next_attempt_at' => now()->addSeconds($config['timeout'] + 30),
        ]);
        if (! $claimed) {
            return;
        }
        $delivery->refresh();

        try {
            $headers = [$config['reference_header'] => $delivery->reference];
            if (filled($config['token'])) {
                $headers[$config['auth_header']] = trim($config['auth_scheme'].' '.$config['token']);
            }
            $response = Http::asJson()
                ->acceptJson()
                ->withoutRedirecting()
                ->connectTimeout($config['connect_timeout'])
                ->timeout($config['timeout'])
                ->withHeaders($headers)
                ->post($config['endpoint'], $payload);
        } catch (ConnectionException) {
            $this->retry($delivery, $config, 'connection_failure');

            return;
        } catch (Throwable) {
            $this->finish($delivery, ['status' => 'review', 'last_error' => 'request_failure']);

            return;
        }

        if (in_array($response->status(), [408, 425, 429], true) || $response->serverError()) {
            $this->retry($delivery, $config, 'temporary_http_failure', $response);

            return;
        }

        if (in_array($response->status(), $config['accepted_statuses'], true)) {
            $this->finish($delivery, [
                'status' => 'delivered',
                'last_error' => null,
                'last_http_status' => $response->status(),
                'delivered_at' => now(),
            ]);

            return;
        }

        $this->finish($delivery, [
            'status' => 'review',
            'last_http_status' => $response->status(),
            'last_error' => match ($response->status()) {
                401, 403 => 'authentication_rejected',
                409 => 'conflict',
                400, 422 => 'validation_rejected',
                default => 'unexpected_http_status',
            },
        ]);
    }

    private function retry(LeadDelivery $delivery, array $config, string $error, ?Response $response = null): void
    {
        $delays = $config['retry_delays'];
        $delay = $delays[min($delivery->attempts - 1, count($delays) - 1)];
        $delay += random_int(0, max(1, (int) ($delay / 10)));
        $delay = min($delay, 604800);
        $retryAfter = $response?->header('Retry-After');
        if (is_string($retryAfter) && $retryAfter !== '') {
            $wait = ctype_digit($retryAfter)
                ? (int) $retryAfter
                : max(0, (strtotime($retryAfter) ?: 0) - now()->timestamp);
            $delay = max($delay, min($wait, 604801));
        }
        $review = $delivery->attempts >= $config['max_attempts'] || $delay > 604800;
        $this->finish($delivery, [
            'status' => $review ? 'review' : 'pending',
            'last_error' => $review ? 'retry_limit_reached' : $error,
            'last_http_status' => $response?->status(),
            'next_attempt_at' => $review ? null : now()->addSeconds($delay),
        ]);
    }

    private function finish(LeadDelivery $delivery, array $values): void
    {
        // A stale process cannot overwrite the outcome of a newer claim.
        $updated = LeadDelivery::query()
            ->whereKey($delivery->id)
            ->where('processing_token', $delivery->processing_token)
            ->update($values + ['processing_token' => null, 'next_attempt_at' => null]);
        if ($updated && ($values['status'] ?? '') === 'review') {
            Log::warning('Lead delivery needs review.', [
                'reference' => $delivery->reference,
                'reason' => $values['last_error'],
            ]);
        }
    }
}
