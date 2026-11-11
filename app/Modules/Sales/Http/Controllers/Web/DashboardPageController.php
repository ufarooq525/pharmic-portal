<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Access\Enums\Role;
use App\Modules\Sales\Services\DashboardData;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardPageController
{
    public function __invoke(Request $request, DashboardData $data): View|RedirectResponse
    {
        if ($request->user()->role === Role::MedicalRep) {
            return redirect()->route('my.index', $request->only('period'));
        }

        $period = Period::fromRequest($request);

        return view('sales::dashboard', ['period' => $period] + $data->for($period));
    }
}
