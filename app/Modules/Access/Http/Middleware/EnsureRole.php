<?php

namespace App\Modules\Access\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:owner,accounts'). The owner always passes. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role?->value;

        abort_unless($role === 'owner' || in_array($role, $roles, true), 403, 'Your role cannot do this.');

        return $next($request);
    }
}
