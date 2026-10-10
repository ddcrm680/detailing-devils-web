<?php

namespace App\Http\Controllers\Franchise;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    // Temporary: OTP sending is not implemented yet.
    public function send(): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'OTP service is not available yet.',
        ], 503);
    }

    // Temporary: OTP verification is not implemented yet.
    public function verify(): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'OTP verification is not available yet.',
        ], 503);
    }
}
