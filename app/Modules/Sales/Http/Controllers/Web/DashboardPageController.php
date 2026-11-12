<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Distribution\Repositories\MedicalRepRepository;
use App\Modules\Sales\Http\Requests\ReportRequest;
use App\Modules\Sales\Repositories\DashboardRepository;
use Illuminate\View\View;

class DashboardPageController
{
    /** Same distributor, product and rep filters as the reports, so both screens agree. */
    public function __invoke(ReportRequest $request, DashboardRepository $dashboard, DistributorRepository $distributors, ProductRepository $products, MedicalRepRepository $reps): View
    {
        $period = $request->period();
        $user = $request->user();
        $scope = $user->visibleDistributorIds();

        return view('sales::dashboard', ['period' => $period]
            + $dashboard->for($period, $scope, $request->filters())
            + ['money' => $dashboard->money($period, $scope, withPayroll: $user->hasRole('accounts'))]
            + ['options' => [
                'distributor' => ['All distributors', $distributors->visible($scope, ['id', 'name'])],
                'rep' => ['All medical reps', $reps->all(['id', 'name'], orderBy: 'name')],
                'product' => ['All products', $products->all(['id', 'name'], orderBy: 'name')],
            ]]);
    }
}
