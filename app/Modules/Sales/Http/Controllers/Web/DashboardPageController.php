<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Ledger\Services\LedgerBuilder;
use App\Modules\Payroll\Services\PayrollBuilder;
use App\Modules\Sales\Services\DashboardData;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardPageController
{
    public function __invoke(Request $request, DashboardData $data, LedgerBuilder $ledger, PayrollBuilder $payroll): View|RedirectResponse
    {
        if ($request->user()->role === Role::MedicalRep) {
            return redirect()->route('my.index', $request->only('period'));
        }

        $period = Period::fromRequest($request);

        $scope = $request->user()->visibleDistributorIds();
        $inScope = fn ($q) => $q->when($scope, fn ($q, $ids) => $q->whereIn('distributor_id', $ids));

        // Money side of the month: not cached, it changes with every payment and invoice.
        $ledger = collect($ledger->month($period))->filter(fn ($r) => $scope === null || in_array($r['distributor']->id, $scope, true));
        $invoices = $inScope(Invoice::where('kind', Invoice::INVOICE)->where('status', Invoice::ISSUED)->whereDate('period', $period->startOfMonth()));

        return view('sales::dashboard', ['period' => $period] + $data->for($period, $scope) + [
            'money' => [
                'outstanding' => $ledger->sum('balance'),
                'received' => $ledger->sum('received'),
                'invoiced' => $ledger->sum('invoiced'),
                'invoices' => (clone $invoices)->count(),
                'invoicesTotal' => (float) (clone $invoices)->sum('total'),
                'payroll' => $request->user()->hasRole('accounts') ? collect($payroll->month($period))->sum('net') : null,
            ],
        ]);
    }
}
