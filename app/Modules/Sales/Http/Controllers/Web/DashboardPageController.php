<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Repositories\InvoiceRepository;
use App\Modules\Ledger\Repositories\LedgerRepository;
use App\Modules\Payroll\Repositories\PayrollRepository;
use App\Modules\Sales\Repositories\DashboardRepository;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardPageController
{
    public function __invoke(Request $request, DashboardRepository $data, LedgerRepository $ledger, PayrollRepository $payroll, InvoiceRepository $invoices): View|RedirectResponse
    {
        if ($request->user()->role === Role::MedicalRep) {
            return redirect()->route('my.index', $request->only('period'));
        }

        $period = Period::fromRequest($request);

        $scope = $request->user()->visibleDistributorIds();
        $ledgerMonth = collect($ledger->month($period, $scope));
        $issued = $invoices->issuedSummary($period, $scope);

        return view('sales::dashboard', ['period' => $period] + $data->for($period, $scope) + [
            // Money side of the month: not cached, it changes with every payment and invoice.
            'money' => [
                'outstanding' => $ledgerMonth->sum('balance'),
                'received' => $ledgerMonth->sum('received'),
                'invoiced' => $ledgerMonth->sum('invoiced'),
                'invoices' => $issued['count'],
                'invoicesTotal' => $issued['total'],
                'payroll' => $request->user()->hasRole('accounts') ? collect($payroll->month($period))->sum('net') : null,
            ],
        ]);
    }
}
