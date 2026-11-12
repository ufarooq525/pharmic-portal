<?php

namespace App\Modules\Ledger\Http\Controllers\Web;

use App\Modules\Distribution\Models\Distributor;
use App\Modules\Distribution\Repositories\DistributorRepository;
use App\Modules\Ledger\Actions\RecordPayment;
use App\Modules\Ledger\Actions\RemovePayment;
use App\Modules\Ledger\Http\Requests\StorePaymentRequest;
use App\Modules\Ledger\Models\Payment;
use App\Modules\Ledger\Repositories\LedgerRepository;
use App\Support\Http\CsvDownload;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Distributor ledgers: confirmed monthly invoices (debit) against payments received (credit). */
class LedgerPageController
{
    public function __construct(private readonly LedgerRepository $ledger) {}

    public function index(Request $request, DistributorRepository $distributors): View
    {
        $allowed = $request->user()->visibleDistributorIds();
        $list = $distributors->visible($allowed);
        $current = $list->firstWhere('id', $request->integer('distributor')) ?? $list->first();
        $period = Period::fromRequest($request);
        $year = $this->year($request);

        return view('ledger::index', [
            'period' => $period,
            'month' => $this->ledger->month($period, $allowed),
            'distributors' => $list,
            'balances' => $this->ledger->balances(),
            'current' => $current,
            'year' => $year,
            'statement' => $current ? $this->ledger->statement($current, $year) : null,
            'canRecord' => $request->user()->hasRole('accounts'),
            'canRemove' => $request->user()->hasRole('owner'),
        ]);
    }

    public function store(StorePaymentRequest $request, Distributor $distributor, RecordPayment $record): RedirectResponse
    {
        $payment = $record->handle($distributor, $request->validated(), $request->user());

        return back()->with('status', 'Payment of '.fmt_rs($payment->amount)." from {$distributor->name} is recorded.");
    }

    public function destroy(Payment $payment, RemovePayment $remove): RedirectResponse
    {
        $remove->handle($payment);

        return back()->with('status', 'Payment of '.fmt_rs($payment->amount).' removed from the ledger.');
    }

    public function export(Request $request, Distributor $distributor): StreamedResponse
    {
        abort_unless($request->user()->canSeeDistributor($distributor->id), 403);
        $year = $this->year($request);
        $s = $this->ledger->statement($distributor, $year);

        $rows = array_merge(
            [['', 'Opening balance', '', '', '', $s['opening']]],
            array_map(fn ($r) => [$r['date']->format('Y-m-d'), $r['what'], $r['detail'], $r['debit'] ?: '', $r['credit'] ?: '', $r['balance']], $s['rows']),
            [['', 'Closing balance', '', $s['debit'], $s['credit'], $s['closing']]],
        );

        return CsvDownload::make(str($distributor->name)->slug()."-ledger-{$year}.csv", ['Date', 'Entry', 'Detail', 'Debit (invoices)', 'Credit (payments)', 'Balance'], $rows);
    }

    private function year(Request $request): int
    {
        $year = $request->integer('year');

        return $year >= 2020 && $year <= 2100 ? $year : (int) Period::fromRequest($request)->format('Y');
    }
}
