<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DeployController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = env('DEPLOY_TOKEN');

        abort_unless(
            is_string($token)
            && $token !== ''
            && hash_equals($token, (string) $request->query('token')),
            403,
            'Unauthorized'
        );

        try {

            // Artisan::call('migrate:fresh', [
            //     '--force' => true,
            // ]);

              Artisan::call('migrate', [
                '--force' => true,
            ]);

            return response()->json([
                'status' => 'success',
                'mode' => 'migrate',
                'output' => Artisan::output(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
