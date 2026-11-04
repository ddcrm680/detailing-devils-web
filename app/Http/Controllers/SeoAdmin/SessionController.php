<?php

namespace App\Http\Controllers\SeoAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class SessionController extends Controller
{
    public function create()
    {
        return Auth::guard('web')->check() && Gate::allows('manage-seo')
            ? redirect()->route('admin.seo.index')
            : view('admin.login');
    }

    public function store(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'max:4096'],
        ]);
        $email = mb_strtolower(trim($input['email']));
        $keys = [
            'seo-login-ip:'.hash('sha256', $request->ip() ?? ''),
            'seo-login-account:'.hash('sha256', $email),
        ];
        foreach ($keys as $i => $key) {
            if (RateLimiter::tooManyAttempts($key, $i === 0 ? 10 : 5)) {
                return response()
                    ->view(
                        'admin.login',
                        ['loginError' => 'Too many attempts. Wait a minute before trying again.'],
                        429
                    )
                    ->header(
                        'Retry-After',
                        60
                    );
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, 60);
        }
        if (
            ! Auth::guard('web')->attempt([
                'email' => $email,
                'password' => $input['password'],
                'role' => User::SEO_ROLES,
            ])
        ) {
            throw ValidationException::withMessages(['email' => 'These sign-in details could not be verified.']);
        }
        foreach ($keys as $key) {
            RateLimiter::clear($key);
        }
        $request->session()->regenerate();

        return redirect()->route('admin.seo.index');
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
