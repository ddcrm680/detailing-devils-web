<?php

namespace App\Http\Middleware;

use App\Services\Seo\Admin\SeoStorage;
use Closure;
use Illuminate\Http\Request;
use Throwable;

/** SEO storage readiness only; shared Admin security is checked by AdminReady. */
final class SeoAdminReady
{
    public function handle(Request $request, Closure $next)
    {
        try {
            app(SeoStorage::class)->state();
        } catch (Throwable) {
            abort(404);
        }

        return $next($request);
    }
}
