<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Sales\Repositories\DashboardRepository;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardPageController
{
    public function __invoke(Request $request, DashboardRepository $dashboard): View
    {
        $period = Period::fromRequest($request);
        $user = $request->user();
        $scope = $user->visibleDistributorIds();

        return view('sales::dashboard', ['period' => $period]
            + $dashboard->for($period, $scope)
            + ['money' => $dashboard->money($period, $scope, withPayroll: $user->hasRole('accounts'))]);
    }
}
