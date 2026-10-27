<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every web request for signed-in users:
 * - an account deactivated mid-session is signed out;
 * - an account with a temporary password can only change it (or log out) until it does.
 */
class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('This account is inactive. Please contact LWUA.')]);
        }

        if ($user->must_change_password && ! $request->routeIs('password.setup', 'password.update', 'logout')) {
            return redirect()->route('password.setup');
        }

        return $next($request);
    }
}
