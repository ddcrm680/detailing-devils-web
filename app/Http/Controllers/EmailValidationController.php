<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class EmailValidationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->session()->forget('email_validation.proof');

        $email = $request->input('email');
        $validator = Validator::make(
            ['email' => is_string($email) ? trim($email) : $email],
            ['email' => ['required', 'string', 'email:rfc', 'max:254']]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'result' => 'invalid',
                'message' => 'Enter a valid email address.',
                'errors' => ['email' => ['Enter a valid email address.']],
            ], 422);
        }

        $apiKey = config('services.zerobounce.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return $this->unavailable();
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->post('https://api.zerobounce.net/v2/validate', [
                    'api_key' => $apiKey,
                    'email' => $validator->validated()['email'],
                ]);
        } catch (ConnectionException) {
            return $this->unavailable();
        }

        if (! $response->successful()) {
            return $this->unavailable();
        }

        $status = $response->json('status');

        if ($status === 'valid') {
            $normalizedEmail = strtolower($validator->validated()['email']);
            $request->session()->put('email_validation.proof', [
                'email_hash' => hash_hmac('sha256', $normalizedEmail, (string) config('app.key')),
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);

            return response()->json(['success' => true, 'result' => 'valid', 'message' => 'Valid email.']);
        }

        if (in_array($status, ['invalid', 'catch-all', 'unknown', 'spamtrap', 'abuse', 'do_not_mail'], true)) {
            return response()->json(['success' => false, 'result' => 'invalid', 'message' => 'Email could not be verified as deliverable.']);
        }

        return $this->unavailable();
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'result' => 'unavailable',
            'message' => 'Email validation is temporarily unavailable.',
        ], 503);
    }
}
