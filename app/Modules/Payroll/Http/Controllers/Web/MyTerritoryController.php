<?php

namespace App\Modules\Payroll\Http\Controllers\Web;

use App\Modules\Access\Enums\Role;
use App\Modules\Payroll\Repositories\PayrollRepository;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A medical rep's own view: their customers, this month's sales and incentive, and payslip. */
class MyTerritoryController
{
    public function __invoke(Request $request, PayrollRepository $payroll): View
    {
        $user = $request->user();
        abort_unless($user->role === Role::MedicalRep && $user->medical_rep_id, 403, 'This page is for medical reps.');
        $period = Period::fromRequest($request);
        $rep = $user->medicalRep()->with('territory')->firstOrFail();

        return view('payroll::my', [
            'period' => $period,
            'rep' => $rep,
            'customers' => $payroll->territoryCustomers($period, $rep->id),
            'pay' => $payroll->month($period, $rep->id)[0] ?? null,
        ]);
    }
}
