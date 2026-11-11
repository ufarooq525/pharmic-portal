<?php

namespace App\Modules\Ledger\Services;

use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Models\Import;
use App\Modules\Ledger\Models\Payment;
use App\Modules\Sales\Models\SaleLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A distributor's account with Pharmic LH: opening balance, plus each confirmed month's invoice
 * (trade-price value + sales tax + withholding tax, as on the invoice) as a debit, minus payments
 * received (credit). Only confirmed imports count, so undoing an import takes its debit out too.
 */
class LedgerBuilder
{
    /** What the distributor is billed per sale line: trade-price value plus both taxes. */
    public const INVOICE_TOTAL = 'sale_lines.value_tp + sale_lines.sales_tax + sale_lines.withholding_tax';

    /**
     * One row per distributor for a month, like the Skyline "Balances" table.
     *
     * @return list<array{distributor: Distributor, opening: float, invoiced: float, due: float, received: float, balance: float, status: ?string}>
     */
    public function month(CarbonImmutable $period): array
    {
        $start = $period->startOfMonth()->toDateString();
        $end = $period->endOfMonth()->toDateString();
        $confirmed = fn () => DB::table('sale_lines')->join('imports', 'imports.id', '=', 'sale_lines.import_id')
            ->where('imports.status', Import::CONFIRMED)->groupBy('sale_lines.distributor_id')
            ->selectRaw('sale_lines.distributor_id, SUM('.self::INVOICE_TOTAL.') as amount');

        $before = $confirmed()->where('sale_lines.period', '<', $start)->pluck('amount', 'distributor_id');
        $during = $confirmed()->whereBetween('sale_lines.period', [$start, $end])->pluck('amount', 'distributor_id');
        $paidBefore = Payment::where('paid_on', '<', $start)->groupBy('distributor_id')->selectRaw('distributor_id, SUM(amount) as amount')->pluck('amount', 'distributor_id');
        $paidDuring = Payment::whereBetween('paid_on', [$start, $end])->groupBy('distributor_id')->selectRaw('distributor_id, SUM(amount) as amount')->pluck('amount', 'distributor_id');
        $status = Import::whereBetween('period', [$start, $end])->latest('id')->get(['distributor_id', 'status'])->unique('distributor_id')->pluck('status', 'distributor_id');

        return Distributor::orderBy('name')->get()->map(function (Distributor $d) use ($before, $during, $paidBefore, $paidDuring, $status) {
            $opening = round($d->opening_balance + (float) ($before[$d->id] ?? 0) - (float) ($paidBefore[$d->id] ?? 0), 2);
            $invoiced = round((float) ($during[$d->id] ?? 0), 2);
            $received = round((float) ($paidDuring[$d->id] ?? 0), 2);

            return [
                'distributor' => $d, 'opening' => $opening, 'invoiced' => $invoiced, 'due' => round($opening + $invoiced, 2),
                'received' => $received, 'balance' => round($opening + $invoiced - $received, 2), 'status' => $status[$d->id] ?? null,
            ];
        })->all();
    }

    /** @return array{opening: float, rows: list<array<string, mixed>>, debit: float, credit: float, closing: float} */
    public function statement(Distributor $distributor, int $year): array
    {
        $from = CarbonImmutable::create($year)->startOfYear();
        $to = $from->endOfYear();

        $opening = $distributor->opening_balance
            + $this->sales($distributor)->where('period', '<', $from->toDateString())->sum('amount')
            - Payment::where('distributor_id', $distributor->id)->where('paid_on', '<', $from->toDateString())->sum('amount');

        $entries = $this->sales($distributor)
            ->whereBetween('period', [$from->toDateString(), $to->toDateString()])
            ->map(fn ($s) => [
                'date' => CarbonImmutable::parse($s->period)->endOfMonth(),
                'kind' => 'sale',
                'what' => 'Invoice for '.CarbonImmutable::parse($s->period)->format('F Y'),
                'detail' => $s->original_name,
                'import_id' => $s->import_id,
                'debit' => (float) $s->amount,
                'credit' => 0.0,
            ])
            ->concat(Payment::where('distributor_id', $distributor->id)
                ->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])->get()
                ->map(fn (Payment $p) => [
                    'date' => CarbonImmutable::parse($p->paid_on),
                    'kind' => 'payment',
                    'what' => 'Payment · '.(Payment::METHODS[$p->method] ?? $p->method),
                    'detail' => trim(($p->reference ? 'Ref '.$p->reference : '').($p->note ? ' · '.$p->note : ''), ' ·'),
                    'payment_id' => $p->id,
                    'debit' => 0.0,
                    'credit' => $p->amount,
                ]))
            // Same day: the sale posts before the payment against it.
            ->sortBy(fn ($e) => $e['date']->format('Ymd').($e['kind'] === 'sale' ? '0' : '1'))
            ->values();

        $balance = round($opening, 2);
        $rows = $entries->map(function ($e) use (&$balance) {
            $balance = round($balance + $e['debit'] - $e['credit'], 2);

            return $e + ['balance' => $balance];
        })->all();

        return [
            'opening' => round($opening, 2),
            'rows' => $rows,
            'debit' => round($entries->sum('debit'), 2),
            'credit' => round($entries->sum('credit'), 2),
            'closing' => $balance,
        ];
    }

    /** What each distributor owes today. @return Collection<int, float> keyed by distributor id */
    public function balances(): Collection
    {
        $sales = SaleLine::query()->join('imports', 'imports.id', '=', 'sale_lines.import_id')
            ->where('imports.status', Import::CONFIRMED)
            ->groupBy('sale_lines.distributor_id')->selectRaw('sale_lines.distributor_id, SUM('.self::INVOICE_TOTAL.') as amount')
            ->pluck('amount', 'distributor_id');
        $paid = Payment::groupBy('distributor_id')->selectRaw('distributor_id, SUM(amount) as amount')->pluck('amount', 'distributor_id');

        return Distributor::pluck('opening_balance', 'id')
            ->map(fn ($opening, $id) => round((float) $opening + (float) ($sales[$id] ?? 0) - (float) ($paid[$id] ?? 0), 2));
    }

    /** One row per confirmed import: its month and total Ex-FP value. */
    private function sales(Distributor $distributor): Collection
    {
        // Plain rows (not models), so period stays a Y-m-d string that compares and sorts as text.
        return DB::table('sale_lines')->join('imports', 'imports.id', '=', 'sale_lines.import_id')
            ->where('sale_lines.distributor_id', $distributor->id)
            ->where('imports.status', Import::CONFIRMED)
            ->groupBy('sale_lines.import_id', 'sale_lines.period', 'imports.original_name')
            ->selectRaw('sale_lines.import_id, sale_lines.period, imports.original_name, SUM('.self::INVOICE_TOTAL.') as amount')
            ->get()
            ->map(function ($row) {
                $row->period = substr((string) $row->period, 0, 10);

                return $row;
            });
    }
}
