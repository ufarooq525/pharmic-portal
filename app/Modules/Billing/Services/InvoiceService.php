<?php

namespace App\Modules\Billing\Services;

use App\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Builds invoice lines from confirmed sale lines (one row per product), issues them under a
 * running number per month (INV-2610-0001), and cancels an issued invoice with a credit note.
 */
class InvoiceService
{
    /** @return list<array<string, mixed>> */
    public function lines(Distributor $distributor, ?Customer $customer, CarbonImmutable $period): array
    {
        return DB::table('sale_lines')->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->where('sale_lines.distributor_id', $distributor->id)
            ->when($customer, fn ($q) => $q->where('sale_lines.customer_id', $customer->id))
            ->whereBetween('sale_lines.period', [$period->startOfMonth()->toDateString(), $period->endOfMonth()->toDateString()])
            ->groupBy('sale_lines.product_id', 'products.name', 'sale_lines.sales_tax_rate', 'sale_lines.withholding_tax_rate')
            ->orderByRaw('SUM(sale_lines.value_tp) DESC')
            ->selectRaw('sale_lines.product_id, products.name AS product_name, SUM(sale_lines.units) AS units, SUM(sale_lines.bonus_units) AS bonus,
                SUM(sale_lines.value_tp) AS value, sale_lines.sales_tax_rate, SUM(sale_lines.sales_tax) AS sales_tax,
                sale_lines.withholding_tax_rate, SUM(sale_lines.withholding_tax) AS withholding_tax')
            ->get()
            ->map(function ($r) {
                $value = round((float) $r->value, 2);
                $st = round((float) $r->sales_tax, 2);
                $wht = round((float) $r->withholding_tax, 2);

                return [
                    'product_id' => (int) $r->product_id, 'product_name' => $r->product_name,
                    'units' => (float) $r->units, 'bonus' => (float) $r->bonus,
                    'rate' => (float) $r->units != 0.0 ? round($value / (float) $r->units, 2) : 0.0,
                    'value' => $value, 'sales_tax_rate' => (float) $r->sales_tax_rate, 'sales_tax' => $st,
                    'withholding_tax_rate' => (float) $r->withholding_tax_rate, 'withholding_tax' => $wht,
                    'total' => round($value + $st + $wht, 2),
                ];
            })->all();
    }

    /** @param list<array<string, mixed>> $lines */
    public function totals(array $lines): array
    {
        $sum = fn ($k) => round(array_sum(array_column($lines, $k)), 2);

        return ['units' => $sum('units'), 'bonus' => $sum('bonus'), 'value' => $sum('value'), 'sales_tax' => $sum('sales_tax'), 'withholding_tax' => $sum('withholding_tax'), 'total' => $sum('total')];
    }

    public function issued(Distributor $distributor, ?Customer $customer, CarbonImmutable $period): ?Invoice
    {
        return Invoice::where('kind', Invoice::INVOICE)->where('status', Invoice::ISSUED)
            ->where('distributor_id', $distributor->id)->where('customer_id', $customer?->id)
            ->whereBetween('period', [$period->startOfMonth()->toDateString(), $period->endOfMonth()->toDateString()])->with('lines')->first();
    }

    public function issue(Distributor $distributor, ?Customer $customer, CarbonImmutable $period, User $user): Invoice
    {
        $lines = $this->lines($distributor, $customer, $period);
        if ($lines === []) {
            throw new DomainException('There are no confirmed sales to invoice for '.$period->format('F Y').'.');
        }

        return DB::transaction(function () use ($distributor, $customer, $period, $user, $lines) {
            if ($this->issued($distributor, $customer, $period)) {
                throw new DomainException('An invoice for this account and month is already issued. Cancel it with a credit note first.');
            }

            $invoice = Invoice::create($this->totals($lines) + [
                'number' => $this->nextNumber('INV', $period),
                'kind' => Invoice::INVOICE,
                'distributor_id' => $distributor->id,
                'customer_id' => $customer?->id,
                'period' => $period->startOfMonth(),
                'status' => Invoice::ISSUED,
                'issued_by' => $user->id,
                'issued_at' => now(),
            ]);
            $invoice->lines()->createMany($lines);

            return $invoice;
        });
    }

    /** Cancels an issued invoice and issues a credit note that reverses every line. */
    public function cancel(Invoice $invoice, string $reason, User $user): Invoice
    {
        if ($invoice->isCreditNote() || $invoice->status !== Invoice::ISSUED) {
            throw new DomainException('Only an issued invoice can be cancelled.');
        }

        return DB::transaction(function () use ($invoice, $reason, $user) {
            $negate = fn (array $a, array $keys) => array_merge($a, array_map(fn ($k) => -1 * (float) $a[$k], array_combine($keys, $keys)));
            $money = ['units', 'bonus', 'value', 'sales_tax', 'withholding_tax', 'total'];

            $note = Invoice::create($negate($invoice->only($money), $money) + [
                'number' => $this->nextNumber('CN', CarbonImmutable::parse($invoice->period)),
                'kind' => Invoice::CREDIT_NOTE,
                'distributor_id' => $invoice->distributor_id,
                'customer_id' => $invoice->customer_id,
                'period' => $invoice->period,
                'status' => Invoice::ISSUED,
                'credit_note_for_id' => $invoice->id,
                'issued_by' => $user->id,
                'issued_at' => now(),
                'cancel_reason' => $reason,
            ]);
            $note->lines()->createMany($invoice->lines->map(fn ($l) => $negate(
                $l->only(['product_id', 'product_name', 'units', 'bonus', 'rate', 'value', 'sales_tax_rate', 'sales_tax', 'withholding_tax_rate', 'withholding_tax', 'total']),
                ['units', 'bonus', 'value', 'sales_tax', 'withholding_tax', 'total'],
            ))->all());

            $invoice->update(['status' => Invoice::CANCELLED, 'cancelled_by' => $user->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);

            return $note;
        });
    }

    private function nextNumber(string $prefix, CarbonImmutable $period): string
    {
        $base = $prefix.'-'.$period->format('ym').'-';
        $last = Invoice::where('number', 'like', $base.'%')->lockForUpdate()->orderByDesc('number')->value('number');

        return $base.str_pad((string) ((int) substr((string) $last, -4) + 1), 4, '0', STR_PAD_LEFT);
    }
}
