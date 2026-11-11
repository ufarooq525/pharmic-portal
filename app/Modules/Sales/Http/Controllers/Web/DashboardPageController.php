<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Sales\Services\DashboardData;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardPageController
{
    public function __invoke(Request $request, DashboardData $data): View
    {
        $period = Period::fromRequest($request);

        return view('sales::dashboard', ['period' => $period] + $data->for($period));
    }
}
