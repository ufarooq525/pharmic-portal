<?php

namespace App\Modules\Ledger\Http\Controllers\Web;

use App\Modules\Access\Models\ActivityLog;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Ledger\Models\Payment;
use App\Modules\Ledger\Services\LedgerBuilder;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Distributor ledgers: confirmed sales (debit) against payments received (credit). */
class LedgerPageController
{
    public function index(Request $request, LedgerBuilder $ledger): View
    {
        $distributors = Distributor::orderBy('name')->get();
        $current = $distributors->firstWhere('id', $request->integer('distributor')) ?? $distributors->first();
        $year = $this->year($request);

        $period = Period::fromRequest($request);

        return view('ledger::index', [
            'period' => $period,
            'month' => $ledger->month($period),
            'distributors' => $distributors,
            'balances' => $ledger->balances(),
            'current' => $current,
            'year' => $year,
            'statement' => $current ? $ledger->statement($current, $year) : null,
            'canRecord' => $request->user()->hasRole('accounts'),
            'canRemove' => $request->user()->hasRole('owner'),
        ]);
    }

    public function store(Request $request, Distributor $distributor): RedirectResponse
    {
        $data = $request->validate([
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $distributor->payments()->create($data + ['recorded_by' => $request->user()->id]);
        ActivityLog::record('Recorded payment', "{$distributor->name}: ".fmt_rs((float) $data['amount'])." on {$data['paid_on']} by {$data['method']}".(empty($data['reference']) ? '' : ", ref {$data['reference']}"));

        return back()->with('status', 'Payment of '.fmt_rs((float) $data['amount'])." from {$distributor->name} is recorded.");
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $payment->delete();
        ActivityLog::record('Removed payment', $payment->distributor->name.': '.fmt_rs($payment->amount).' of '.$payment->paid_on->format('j M Y'));

        return back()->with('status', 'Payment of '.fmt_rs($payment->amount).' removed from the ledger.');
    }

    public function export(Request $request, Distributor $distributor, LedgerBuilder $ledger): StreamedResponse
    {
        $year = $this->year($request);
        $s = $ledger->statement($distributor, $year);

        return response()->streamDownload(function () use ($s) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Entry', 'Detail', 'Debit (sales)', 'Credit (payments)', 'Balance']);
            fputcsv($out, ['', 'Opening balance', '', '', '', $s['opening']]);
            foreach ($s['rows'] as $r) {
                fputcsv($out, [$r['date']->format('Y-m-d'), $r['what'], $r['detail'], $r['debit'] ?: '', $r['credit'] ?: '', $r['balance']]);
            }
            fputcsv($out, ['', 'Closing balance', '', $s['debit'], $s['credit'], $s['closing']]);
            fclose($out);
        }, str($distributor->name)->slug()."-ledger-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function year(Request $request): int
    {
        $year = $request->integer('year');

        return $year >= 2020 && $year <= 2100 ? $year : (int) Period::fromRequest($request)->format('Y');
    }
}
