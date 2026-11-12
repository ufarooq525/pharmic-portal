<?php

namespace App\Modules\Billing\Repositories;

use App\Modules\Billing\Enums\InvoiceKind;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use App\Support\Period;
use App\Support\Repositories\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Invoice queries: lines built from confirmed sales, the issued invoice for an account, numbering. */
class InvoiceRepository extends Repository
{
    protected function model(): string
    {
        return Invoice::class;
    }

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

    public function issuedFor(Distributor $distributor, ?Customer $customer, CarbonImmutable $period): ?Invoice
    {
        return Invoice::where('kind', InvoiceKind::Invoice)->where('status', InvoiceStatus::Issued)
            ->where('distributor_id', $distributor->id)->where('customer_id', $customer?->id)
            ->whereBetween('period', [$period->startOfMonth()->toDateString(), $period->endOfMonth()->toDateString()])->with('lines')->first();
    }

    /** Invoices and credit notes of a month, for the list under the invoice. */
    public function forMonth(CarbonImmutable $period, ?array $distributorIds, int $perPage = 25): LengthAwarePaginator
    {
        return $this->paginate($perPage, fn ($q) => $this->scopeToDistributors(
            $q->with(['distributor:id,name,city', 'customer:id,name,area'])
                ->whereBetween('period', [Period::start($period), Period::end($period)])->orderBy('number'),
            $distributorIds,
        ))->fragment('issued');
    }

    /** Count and total of the invoices issued for a month. @return array{count: int, total: float} */
    public function issuedSummary(CarbonImmutable $period, ?array $distributorIds): array
    {
        $row = $this->scopeToDistributors($this->query(), $distributorIds)
            ->where('kind', InvoiceKind::Invoice)->where('status', InvoiceStatus::Issued)->whereDate('period', $period->startOfMonth())
            ->toBase()->selectRaw('COUNT(*) AS n, COALESCE(SUM(total), 0) AS total')->first();

        return ['count' => (int) $row->n, 'total' => (float) $row->total];
    }

    /** Issued invoices for a distributor and month, e.g. to cancel them when an import is reverted. */
    public function issuedForDistributorMonth(int $distributorId, CarbonImmutable $period)
    {
        return $this->query()->where('kind', InvoiceKind::Invoice)->where('status', InvoiceStatus::Issued)
            ->where('distributor_id', $distributorId)->whereDate('period', $period->startOfMonth())->with('lines')->get();
    }

    /** Next running number for the month: INV-2610-0001, CN-2610-0001. Call inside a transaction. */
    public function nextNumber(InvoiceKind $kind, CarbonImmutable $period): string
    {
        $base = $kind->prefix().'-'.$period->format('ym').'-';
        $last = $this->query()->where('number', 'like', $base.'%')->lockForUpdate()->orderByDesc('number')->value('number');

        return $base.str_pad((string) ((int) substr((string) $last, -4) + 1), 4, '0', STR_PAD_LEFT);
    }
}
