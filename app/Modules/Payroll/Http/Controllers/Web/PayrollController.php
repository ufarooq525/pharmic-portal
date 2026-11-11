<?php

namespace App\Modules\Payroll\Http\Controllers\Web;

use App\Modules\Access\Models\ActivityLog;
use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Services\PayrollBuilder;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController
{
    public function index(Request $request, PayrollBuilder $payroll): View
    {
        $period = Period::fromRequest($request);

        return view('payroll::index', [
            'period' => $period,
            'rows' => $payroll->month($period),
            'canEdit' => $request->user()->hasRole('accounts'),
            'editing' => $request->integer('edit') ?: null,
        ]);
    }

    public function adjust(Request $request, MedicalRep $rep): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'allowance' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'deductions' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $period = CarbonImmutable::createFromFormat('!Y-m', $data['period']);

        PayrollAdjustment::updateOrCreate(
            ['medical_rep_id' => $rep->id, 'period' => $period->toDateString()],
            ['allowance' => $data['allowance'] ?? 0, 'deductions' => $data['deductions'] ?? 0, 'note' => $data['note'] ?? null],
        );
        ActivityLog::record('Changed payroll', "{$rep->name}, {$period->format('F Y')}: allowance ".fmt_rs($data['allowance'] ?? 0).', deductions '.fmt_rs($data['deductions'] ?? 0));

        return redirect()->route('payroll.index', ['period' => $data['period']])->with('status', "{$rep->name}’s {$period->format('F')} pay is updated.");
    }

    /** Payslip to print or save as PDF. A medical rep can open only their own. */
    public function payslip(Request $request, MedicalRep $rep, PayrollBuilder $payroll): View
    {
        $user = $request->user();
        abort_unless($user->hasRole('accounts') || (int) $user->medical_rep_id === $rep->id, 403);
        $period = Period::fromRequest($request);
        $row = $payroll->month($period, $rep->id)[0] ?? abort(404);

        return view('payroll::payslip', ['period' => $period, 'row' => $row, 'breakdown' => $payroll->breakdown($period, $rep->id)]);
    }
}
