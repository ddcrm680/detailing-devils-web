<?php

namespace App\Http\Controllers\Franchise;

use App\Http\Controllers\Controller;
use App\Http\Requests\Franchise\SendOtpRequest;
use App\Http\Requests\Franchise\VerifyOtpRequest;
use App\Services\Franchise\OtpDeliveryService;
use App\Services\Franchise\OtpException;
use App\Services\Franchise\OtpService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class OtpController extends Controller
{
    public function send(SendOtpRequest $request, OtpDeliveryService $delivery): JsonResponse
    {
        if (! $this->available()) {
            return $this->unavailable();
        }

        $purpose = $request->validated('purpose');
        $mobile = $request->normalizedMobile();

        try {
            $encryptedMobile = Crypt::encryptString($mobile);

            if (! $delivery->send($purpose, $mobile, $request->validated('name'))) {
                return $this->unavailable();
            }

            $request->session()->put($this->contextKey($purpose), [
                'mobile' => $encryptedMobile,
                'expires_at' => now()->timestamp + OtpService::CODE_LIFETIME,
            ]);
        } catch (OtpException $exception) {
            return $this->otpError($exception);
        } catch (Throwable) {
            return $this->unavailable();
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent successfully.',
        ]);
    }

    public function verify(VerifyOtpRequest $request, OtpService $otp): JsonResponse
    {
        if (! $this->available()) {
            return $this->unavailable();
        }

        $purpose = $request->validated('purpose');
        $contextKey = $this->contextKey($purpose);
        $context = $request->session()->get($contextKey);

        if (! is_array($context)
            || ! is_string($context['mobile'] ?? null)
            || ! is_int($context['expires_at'] ?? null)
            || $context['expires_at'] <= now()->timestamp) {
            $request->session()->forget($contextKey);

            return $this->invalidCode();
        }

        try {
            $mobile = Crypt::decryptString($context['mobile']);
            $verified = $otp->verifyChallenge($purpose, $mobile, $request->validated('otp'));
        } catch (DecryptException) {
            $request->session()->forget($contextKey);

            return $this->invalidCode();
        } catch (OtpException $exception) {
            return $this->otpError($exception);
        } catch (Throwable) {
            return $this->unavailable();
        }

        if (! $verified) {
            return $this->invalidCode();
        }

        $request->session()->forget($contextKey);

        return response()->json([
            'success' => true,
            'message' => 'Mobile number verified successfully.',
        ]);
    }

    private function contextKey(string $purpose): string
    {
        return "franchise.otp.pending.{$purpose}";
    }

    private function available(): bool
    {
        if (config('services.sms_alert.enabled') !== true || config('services.sms_alert.storage_ready') !== true) {
            return false;
        }

        foreach (['api_key', 'sender'] as $setting) {
            $value = config("services.sms_alert.{$setting}");

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private function otpError(OtpException $exception): JsonResponse
    {
        if ($exception->reason === OtpException::BUSY) {
            return $this->unavailable();
        }

        $message = $exception->reason === OtpException::COOLDOWN
            ? 'Please wait before requesting another code.'
            : 'Too many verification requests. Please try again later.';

        return response()->json(['success' => false, 'message' => $message], 429)
            ->header('Retry-After', (string) max(1, $exception->retryAfter));
    }

    private function invalidCode(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'The verification code is invalid or expired. Please try again or request a new code.',
        ], 422);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'OTP service is temporarily unavailable. Please try again later.',
        ], 503);
    }
}
