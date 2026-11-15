<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Distribution\Models\Distributor;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One place for the distributor data scope on single records.
 *
 * Runs right after route-model binding on every web and API route. Any bound record that belongs to a
 * distributor (a Distributor itself, or anything with a distributor_id: imports, customers, invoices,
 * payments) is refused with 403 when the signed-in user is limited to other distributors. New routes
 * get the check without anyone having to remember it. Lists still filter with visibleDistributorIds().
 */
class EnforceDistributorScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->visibleDistributorIds() !== null) {
            foreach ($request->route()?->parameters() ?? [] as $record) {
                $distributorId = match (true) {
                    $record instanceof Distributor => $record->getKey(),
                    // Read the raw attributes: models without the column (users, roles) would throw in strict mode.
                    $record instanceof Model => $record->getAttributes()['distributor_id'] ?? null,
                    default => null,
                };

                abort_if($distributorId !== null && ! $user->canSeeDistributor((int) $distributorId), 403);
            }
        }

        return $next($request);
    }
}
