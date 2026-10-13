<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class LocationController extends Controller
{
    public function countries(): JsonResponse
    {
        try {
            $countries = DB::table('tbl_country_phone')
                ->orderBy('countryname')
                ->get(['id', 'countryname as name']);

            return response()->json(['data' => $countries]);
        } catch (Throwable) {
            return $this->locationsUnavailable();
        }
    }

    public function states(Request $request): JsonResponse
    {
        $countryId = $request->validate(['country_id' => ['required', 'integer', 'min:1']])['country_id'];

        try {
            if (! DB::table('tbl_country_phone')->where('id', $countryId)->exists()) {
                return response()->json(['message' => 'Country not found.'], 404);
            }

            $states = DB::table('tbl_states')
                ->where('country_id', $countryId)
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json(['data' => $states]);
        } catch (Throwable) {
            return $this->locationsUnavailable();
        }
    }

    public function cities(Request $request): JsonResponse
    {
        $stateId = $request->validate(['state_id' => ['required', 'integer', 'min:1']])['state_id'];

        try {
            if (! DB::table('tbl_states')->where('id', $stateId)->exists()) {
                return response()->json(['message' => 'State not found.'], 404);
            }

            $cities = DB::table('tbl_cities')
                ->where('state_id', $stateId)
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json(['data' => $cities]);
        } catch (Throwable) {
            return $this->locationsUnavailable();
        }
    }

    private function locationsUnavailable(): JsonResponse
    {
        return response()->json(['message' => 'Locations are temporarily unavailable.'], 503);
    }
}
