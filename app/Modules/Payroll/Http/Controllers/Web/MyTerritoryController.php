<?php

namespace App\Modules\Payroll\Http\Controllers\Web;

use App\Modules\Access\Enums\Role;
use App\Modules\Payroll\Services\PayrollBuilder;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** A medical rep's own view: their customers, this month's sales and incentive, and payslip. */
class MyTerritoryController
{
    public function __invoke(Request $request, PayrollBuilder $payroll): View
    {
        $user = $request->user();
        abort_unless($user->role === Role::MedicalRep && $user->medical_rep_id, 403, 'This page is for medical reps.');
        $period = Period::fromRequest($request);
        $rep = $user->medicalRep()->with('territory')->firstOrFail();

        $customers = DB::table('customer_medical_rep as cmr')
            ->join('customers', 'customers.id', '=', 'cmr.customer_id')
            ->join('distributors', 'distributors.id', '=', 'customers.distributor_id')
            ->leftJoin('sale_lines', fn ($j) => $j->on('sale_lines.customer_id', '=', 'customers.id')
                ->whereBetween('sale_lines.period', [Period::start($period), Period::end($period)]))
            ->where('cmr.medical_rep_id', $rep->id)
            ->groupBy('customers.id', 'customers.name', 'customers.area', 'distributors.name', 'cmr.share_percent')
            ->orderByRaw('COALESCE(SUM(sale_lines.value_exfp), 0) DESC')
            ->selectRaw('customers.name, customers.area, distributors.name AS distributor, cmr.share_percent,
                COALESCE(SUM(sale_lines.units), 0) AS units, COALESCE(SUM(sale_lines.bonus_units), 0) AS bonus,
                COALESCE(SUM(sale_lines.value_exfp * cmr.share_percent / 100), 0) AS sales,
                COALESCE(SUM(sale_lines.incentive * cmr.share_percent / 100), 0) AS incentive')
            ->get();

        return view('payroll::my', ['period' => $period, 'rep' => $rep, 'customers' => $customers, 'pay' => $payroll->month($period, $rep->id)[0] ?? null]);
    }
}
