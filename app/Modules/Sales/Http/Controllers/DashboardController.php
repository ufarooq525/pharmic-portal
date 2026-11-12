<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Http\Resources\DashboardResource;
use App\Modules\Sales\Repositories\DashboardRepository;
use App\Support\Http\Requests\PeriodRequest;

/** JSON dashboard (under /api/v1); the same figures as the web dashboard, scoped to the user's distributors. */
class DashboardController
{
    public function __invoke(PeriodRequest $request, DashboardRepository $dashboard): DashboardResource
    {
        $period = $request->period();

        return new DashboardResource(['period' => $period->format('Y-m')] + $dashboard->for($period, $request->user()->visibleDistributorIds()));
    }
}
