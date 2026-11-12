<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Http\Resources\DashboardResource;
use App\Modules\Sales\Repositories\DashboardRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** JSON dashboard (under /api/v1); the same figures as the web dashboard, scoped to the user's distributors. */
class DashboardController
{
    public function __invoke(Request $request, DashboardRepository $dashboard): DashboardResource
    {
        $period = CarbonImmutable::createFromFormat('!Y-m', $request->validate(['period' => ['required', 'date_format:Y-m']])['period']);

        return new DashboardResource(['period' => $period->format('Y-m')] + $dashboard->for($period, $request->user()->visibleDistributorIds()));
    }
}
