<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('permission:imports.confirm'). An unknown permission name is a coding
 * mistake and fails loudly (Permission::from throws). The Owner always passes.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless((bool) $request->user()?->hasPermission(Permission::from($permission)), 403, 'You don’t have permission to do this. Ask the owner if you need it.');

        return $next($request);
    }
}
