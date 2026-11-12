<?php

namespace App\Modules\Access\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Deactivating a user ends their open sessions on their next click, not only their next sign-in. */
class LogOutDeactivatedUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            if ($user->currentAccessToken() ?? null) {
                abort(401, 'This account is deactivated.');
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account is deactivated.']);
        }

        return $next($request);
    }
}
