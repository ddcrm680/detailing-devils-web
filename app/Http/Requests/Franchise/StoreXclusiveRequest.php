<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Http\Exceptions\HttpResponseException;

class StoreXclusiveRequest extends StoreFofoRequest
{
    public function authorize(): bool
    {
        // Reject the disabled endpoint before inherited location rules can query the CRM.
        if (! config('services.xclusive.submission_enabled')) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'X-Clusive applications are not accepting submissions yet.',
            ], 503));
        }

        return parent::authorize();
    }
}
