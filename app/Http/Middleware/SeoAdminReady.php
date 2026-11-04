<?php

namespace App\Http\Middleware;

use App\Services\Seo\Admin\SeoStorage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Readiness/headers only; Laravel supplies authentication, sessions and CSRF. */
final class SeoAdminReady
{
    public function handle(Request $request, Closure $next)
    {
        if (! app()->environment(['local', 'testing'])) {
            abort_unless(
                ! config('app.debug') && $request->isSecure()
                && in_array($request->getSchemeAndHttpHost(), [
                    'https://www.detailingdevils.com',
                    'https://detailing-devils-web.wasmer.app',
                ], true),
                404
            );
        }
        try {
            abort_unless(Schema::hasColumns('users', ['id', 'email', 'password', 'role']), 404);
            app(SeoStorage::class)->state();
        } catch (Throwable) {
            abort(404);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'none'; frame-ancestors 'self'; form-action 'self'; object-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:"
        );

        return $response;
    }
}
