<?php

namespace App\Modules\Payroll\Http\Controllers\Web;

use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Payroll\Actions\AdjustPayroll;
use App\Modules\Payroll\Http\Requests\AdjustPayrollRequest;
use App\Modules\Payroll\Repositories\PayrollRepository;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController
{
    public function __construct(private readonly PayrollRepository $payroll) {}

    public function index(Request $request): View
    {
        $period = Period::fromRequest($request);

        return view('payroll::index', [
            'period' => $period,
            'rows' => $this->payroll->month($period),
            'canEdit' => $request->user()->hasRole('accounts'),
            'editing' => $request->integer('edit') ?: null,
        ]);
    }

    public function adjust(AdjustPayrollRequest $request, MedicalRep $rep, AdjustPayroll $adjust): RedirectResponse
    {
        $period = $request->period();
        $adjust->handle($rep, $period, (float) $request->validated('allowance'), (float) $request->validated('deductions'), $request->validated('note'));

        return redirect()->route('payroll.index', ['period' => $period->format('Y-m')])->with('status', "{$rep->name}’s {$period->format('F')} pay is updated.");
    }

    /** Payslip to print or save as PDF. A medical rep can open only their own. */
    public function payslip(Request $request, MedicalRep $rep): View
    {
        $user = $request->user();
        abort_unless($user->hasRole('accounts') || (int) $user->medical_rep_id === $rep->id, 403);
        $period = Period::fromRequest($request);
        $row = $this->payroll->month($period, $rep->id)[0] ?? abort(404);

        return view('payroll::payslip', ['period' => $period, 'row' => $row, 'breakdown' => $this->payroll->breakdown($period, $rep->id)]);
    }
}
