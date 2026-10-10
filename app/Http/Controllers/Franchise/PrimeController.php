<?php

namespace App\Http\Controllers\Franchise;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class PrimeController extends Controller
{
    public function create(): View
    {
        return view('forms.prime', [
            'states' => config('form_options.common.states'),
            'primeOptions' => config('form_options.prime'),
        ]);
    }

    // Temporary: application submission is not implemented yet.
    public function store(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Prime applications are not available yet.',
        ], 503);
    }
}
