<?php

namespace App\Services\Franchise;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use LogicException;

class OtpService
{
    public const PURPOSES = ['prime', 'fofo'];

    public const CODE_LIFETIME = 600;

    private const VERIFICATION_LIFETIME = 600;

    private const SEND_WINDOW = 3600;

    private const SEND_COOLDOWN = 30;

    private const SESSION_SEND_LIMIT = 5;

    private const PHONE_SEND_LIMIT = 10;

    private const MAX_VERIFICATION_ATTEMPTS = 5;

    private const LOCK_LIFETIME = 15;

    public function __construct(
        private readonly CacheManager $cache,
        private readonly SessionManager $sessions,
        private readonly PhoneNumberNormalizer $phones,
    ) {}

    /**
     * Return the code and cancellation ID only to the caller responsible for delivery. Never log either.
     * Creating another challenge for this session and purpose supersedes the old one.
     */
    public function createChallenge(string $purpose, string $mobile): OtpChallenge
    {
        [$sessionKey, $phoneKey, $phoneFingerprint] = $this->keys($purpose, $mobile);
        $store = $this->cache->store();

        try {
            return $store->lock("{$phoneKey}:lock", self::LOCK_LIFETIME)->block(3, function () use ($store, $sessionKey, $phoneKey, $phoneFingerprint): OtpChallenge {
                return $store->lock("{$sessionKey}:lock", self::LOCK_LIFETIME)->block(3, function () use ($store, $sessionKey, $phoneKey, $phoneFingerprint): OtpChallenge {
                    $now = now()->timestamp;
                    $sessionQuotaKey = "{$sessionKey}:sends";
                    $phoneQuotaKey = "{$phoneKey}:sends";
                    $sessionQuota = $this->quota($store->get($sessionQuotaKey), $now);
                    $phoneQuota = $this->quota($store->get($phoneQuotaKey), $now);

                    if ($sessionQuota['next_at'] > $now) {
                        throw new OtpException(OtpException::COOLDOWN, $sessionQuota['next_at'] - $now);
                    }

                    if ($sessionQuota['count'] >= self::SESSION_SEND_LIMIT || $phoneQuota['count'] >= self::PHONE_SEND_LIMIT) {
                        $retryAfter = max(
                            $sessionQuota['count'] >= self::SESSION_SEND_LIMIT ? $sessionQuota['expires_at'] - $now : 0,
                            $phoneQuota['count'] >= self::PHONE_SEND_LIMIT ? $phoneQuota['expires_at'] - $now : 0,
                        );

                        throw new OtpException(OtpException::SEND_LIMIT, $retryAfter);
                    }

                    $previous = $store->get($sessionKey);

                    do {
                        $code = (string) random_int(100000, 999999);
                    } while (is_array($previous) && isset($previous['code_hash']) && Hash::check($code, $previous['code_hash']));

                    $challengeId = bin2hex(random_bytes(16));

                    // Count the attempt before making the challenge available.
                    $this->storeOrFail($store, $sessionQuotaKey, [
                        'count' => $sessionQuota['count'] + 1,
                        'expires_at' => $sessionQuota['expires_at'],
                        'next_at' => $now + self::SEND_COOLDOWN,
                    ], $sessionQuota['expires_at'] - $now);
                    $this->storeOrFail($store, $phoneQuotaKey, [
                        'count' => $phoneQuota['count'] + 1,
                        'expires_at' => $phoneQuota['expires_at'],
                        'next_at' => 0,
                    ], $phoneQuota['expires_at'] - $now);

                    $this->storeOrFail($store, $sessionKey, [
                        'state' => 'pending',
                        'id' => $challengeId,
                        'phone' => $phoneFingerprint,
                        'code_hash' => Hash::make($code),
                        'attempts' => 0,
                        'expires_at' => $now + self::CODE_LIFETIME,
                    ], self::CODE_LIFETIME);

                    return new OtpChallenge($challengeId, $code);
                });
            });
        } catch (LockTimeoutException) {
            throw new OtpException(OtpException::BUSY);
        }
    }

    /** Revoke only this still-pending challenge after a failed delivery. */
    public function cancelChallenge(string $purpose, string $mobile, string $challengeId): bool
    {
        if (! preg_match('/\A[0-9a-f]{32}\z/', $challengeId)) {
            return false;
        }

        [$sessionKey, , $phoneFingerprint] = $this->keys($purpose, $mobile);
        $store = $this->cache->store();

        return $this->withSessionLock($sessionKey, function () use ($store, $sessionKey, $phoneFingerprint, $challengeId): bool {
            $now = now()->timestamp;
            $challenge = $store->get($sessionKey);

            if (! is_array($challenge)
                || ($challenge['state'] ?? null) !== 'pending'
                || ($challenge['id'] ?? null) !== $challengeId
                || ($challenge['phone'] ?? null) !== $phoneFingerprint
                || ! is_int($challenge['expires_at'] ?? null)
                || $challenge['expires_at'] <= $now) {
                return false;
            }

            $this->storeOrFail($store, $sessionKey, ['state' => 'cancelled'], $challenge['expires_at'] - $now);

            // Permit an immediate retry, while counting failed delivery attempts
            // toward the existing hourly session and phone abuse limits.
            $quotaKey = "{$sessionKey}:sends";
            $quota = $store->get($quotaKey);

            if (is_array($quota) && is_int($quota['expires_at'] ?? null) && $quota['expires_at'] > $now) {
                $quota['next_at'] = 0;
                $this->storeOrFail($store, $quotaKey, $quota, $quota['expires_at'] - $now);
            }

            return true;
        });
    }

    public function verifyChallenge(string $purpose, string $mobile, string $code): bool
    {
        [$sessionKey, , $phoneFingerprint] = $this->keys($purpose, $mobile);

        return $this->withSessionLock($sessionKey, function () use ($sessionKey, $phoneFingerprint, $code): bool {
            $store = $this->cache->store();
            $challenge = $store->get($sessionKey);
            $now = now()->timestamp;

            if (! is_array($challenge) || ($challenge['state'] ?? null) !== 'pending' || ! is_string($challenge['phone'] ?? null) || ! hash_equals($challenge['phone'], $phoneFingerprint)) {
                return false;
            }

            if (! is_int($challenge['expires_at'] ?? null) || $challenge['expires_at'] <= $now) {
                $store->forget($sessionKey);

                return false;
            }

            if (($challenge['attempts'] ?? 0) >= self::MAX_VERIFICATION_ATTEMPTS) {
                return false;
            }

            if (! is_string($challenge['code_hash'] ?? null) || ! preg_match('/\A[0-9]{6}\z/', $code) || ! Hash::check($code, $challenge['code_hash'])) {
                $challenge['attempts']++;

                if ($challenge['attempts'] >= self::MAX_VERIFICATION_ATTEMPTS) {
                    $challenge = ['state' => 'locked', 'expires_at' => $challenge['expires_at']];
                }

                $this->storeOrFail($store, $sessionKey, $challenge, $challenge['expires_at'] - $now);

                return false;
            }

            $this->storeOrFail($store, $sessionKey, [
                'state' => 'verified',
                'phone' => $phoneFingerprint,
                'expires_at' => $now + self::VERIFICATION_LIFETIME,
            ], self::VERIFICATION_LIFETIME);

            return true;
        });
    }

    /** Consume the proof once, in the future application-saving action. */
    public function consumeVerification(string $purpose, string $mobile): bool
    {
        [$sessionKey, , $phoneFingerprint] = $this->keys($purpose, $mobile);

        return $this->withSessionLock($sessionKey, function () use ($sessionKey, $phoneFingerprint): bool {
            $store = $this->cache->store();
            $proof = $store->get($sessionKey);

            if (! is_array($proof) || ($proof['state'] ?? null) !== 'verified' || ! is_string($proof['phone'] ?? null) || ! hash_equals($proof['phone'], $phoneFingerprint)) {
                return false;
            }

            $remaining = is_int($proof['expires_at'] ?? null) ? $proof['expires_at'] - now()->timestamp : 0;

            if ($remaining <= 0) {
                return false;
            }

            $this->storeOrFail($store, $sessionKey, ['state' => 'consumed'], $remaining);

            return true;
        });
    }

    private function withSessionLock(string $sessionKey, callable $operation): bool
    {
        try {
            return $this->cache->store()->lock("{$sessionKey}:lock", self::LOCK_LIFETIME)->block(3, $operation);
        } catch (LockTimeoutException) {
            throw new OtpException(OtpException::BUSY);
        }
    }

    /** @return array{string, string, string} */
    private function keys(string $purpose, string $mobile): array
    {
        if (! in_array($purpose, self::PURPOSES, true)) {
            throw new InvalidArgumentException('Unknown franchise form type.');
        }

        $normalized = $this->phones->normalizeMobile($mobile);

        if ($normalized === null) {
            throw new InvalidArgumentException('Invalid international mobile number.');
        }

        $sessionId = $this->sessions->driver()->getId();
        $appKey = config('app.key');

        if (! is_string($sessionId) || $sessionId === '' || ! is_string($appKey) || $appKey === '') {
            throw new LogicException('OTP requires an active session and application key.');
        }

        $sessionKey = 'franchise:otp:session:'.hash_hmac('sha256', "{$purpose}|{$sessionId}", $appKey);
        // One phone quota spans Prime and FOFO so switching forms cannot bypass it.
        $phoneFingerprint = hash_hmac('sha256', $normalized, $appKey);
        $phoneKey = 'franchise:otp:phone:'.$phoneFingerprint;

        return [$sessionKey, $phoneKey, $phoneFingerprint];
    }

    /** @return array{count: int, expires_at: int, next_at: int} */
    private function quota(mixed $saved, int $now): array
    {
        if (! is_array($saved) || ($saved['expires_at'] ?? 0) <= $now) {
            return ['count' => 0, 'expires_at' => $now + self::SEND_WINDOW, 'next_at' => 0];
        }

        return $saved;
    }

    private function storeOrFail(Repository $store, string $key, array $value, int $seconds): void
    {
        if ($seconds <= 0 || ! $store->put($key, $value, $seconds)) {
            throw new LogicException('OTP cache is unavailable.');
        }
    }
}
