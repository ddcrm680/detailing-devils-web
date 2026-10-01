<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simple password protection for /admin pages.
 * Set ADMIN_USER and ADMIN_PASSWORD in the .env file.
 */
class AdminBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = (string) env('ADMIN_USER', '');
        $pass = (string) env('ADMIN_PASSWORD', '');

        $ok = $user !== '' && $pass !== ''
            && hash_equals($user, (string) $request->getUser())
            && hash_equals($pass, (string) $request->getPassword());

        if (! $ok) {
            return response('Login required', 401, ['WWW-Authenticate' => 'Basic realm="Detailing Devils admin"']);
        }

        return $next($request);
    }
}
