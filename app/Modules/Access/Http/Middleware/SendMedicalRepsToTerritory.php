<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A medical rep has no dashboard; their home is the "My territory" page. */
class SendMedicalRepsToTerritory
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === Role::MedicalRep) {
            return redirect()->route('my.index', $request->only('period'));
        }

        return $next($request);
    }
}
