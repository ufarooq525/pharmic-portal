<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Enums\Permission;
use App\Modules\Access\Support\Navigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dashboard is everyone's landing page after sign-in. A medical rep goes on to "My territory";
 * staff whose role does not include the dashboard go to the first page they can open.
 */
class SendToHomePage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasPermission(Permission::DashboardView)) {
            $home = Navigation::home($user);
            abort_if($home === null, 403, 'Your role does not include any pages yet. Ask the owner to give you access.');

            return redirect()->route($home, $request->only('period'));
        }

        return $next($request);
    }
}
