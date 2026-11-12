<?php

namespace App\Modules\Sales\Repositories;

use App\Modules\Sales\Models\SaleLine;
use App\Modules\Sales\Enums\ReportType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every report returns the same shape, so one Blade view and one CSV export serve them all:
 *   columns: [key => [label, kind]]   kind: text | num | money | pct
 *   rows:    list of [key => value]
 *   totals:  [key => value] (only for summable columns)
 */
class ReportRepository
{
    /** @param  array{distributor?: ?int, product?: ?int, rep?: ?int, scope?: ?list<int>}  $filters */
    public function build(ReportType $type, CarbonImmutable $period, array $filters): array
    {
        $lines = SaleLine::query()->whereDate('sale_lines.period', $period)
            ->when($filters['scope'] ?? null, fn (Builder $q, $ids) => $q->whereIn('sale_lines.distributor_id', $ids))
            ->when($filters['distributor'] ?? null, fn (Builder $q, $id) => $q->where('sale_lines.distributor_id', $id))
            ->when($filters['product'] ?? null, fn (Builder $q, $id) => $q->where('sale_lines.product_id', $id))
            ->when($filters['rep'] ?? null, fn (Builder $q, $id) => $q->whereHas('customer.medicalReps', fn ($r) => $r->whereKey($id)));

        return match ($type) {
            ReportType::Sales => $this->sales($lines),
            ReportType::Customers => $this->customers($lines),
            ReportType::Bonus => $this->bonus($lines),
            ReportType::Incentive => $this->incentive($period, $filters),
            ReportType::Taxes => $this->taxes($lines),
        };
    }

    private function sales(Builder $lines): array
    {
        $rows = $lines->join('products', 'products.id', '=', 'sale_lines.product_id')->toBase()
            ->selectRaw('products.name AS product, SUM(sale_lines.units) AS units, SUM(sale_lines.bonus_units) AS bonus,
                SUM(sale_lines.value_exfp) AS value_exfp, SUM(sale_lines.value_tp) AS value_tp,
                SUM(sale_lines.units * sale_lines.cost_price) AS cost, SUM(sale_lines.value_exfp - sale_lines.units * sale_lines.cost_price) AS profit')
            ->groupBy('products.id', 'products.name')->orderByDesc('value_exfp')->get()
            ->map(fn ($r) => (array) $r + ['margin' => $r->value_exfp > 0 ? $r->profit / $r->value_exfp * 100 : null]);

        return $this->shape([
            'product' => ['Product', 'text'], 'units' => ['Units', 'num'], 'bonus' => ['Bonus', 'num'], 'value_exfp' => ['Value Ex-FP', 'money'],
            'value_tp' => ['Value TP', 'money'], 'cost' => ['Cost', 'money'], 'profit' => ['Profit', 'money'], 'margin' => ['Margin', 'pct'],
        ], $rows, ['margin' => fn ($t) => $t['value_exfp'] > 0 ? $t['profit'] / $t['value_exfp'] * 100 : null]);
    }

    private function customers(Builder $lines): array
    {
        $rows = $lines->join('customers', 'customers.id', '=', 'sale_lines.customer_id')
            ->join('distributors', 'distributors.id', '=', 'sale_lines.distributor_id')->toBase()
            ->selectRaw('distributors.name AS distributor, customers.name AS customer, customers.area, customers.code,
                SUM(sale_lines.units) AS units, SUM(sale_lines.bonus_units) AS bonus, SUM(sale_lines.value_tp) AS value_tp,
                SUM(sale_lines.sales_tax) AS sales_tax, SUM(sale_lines.withholding_tax) AS withholding_tax')
            ->groupBy('customers.id', 'distributors.name', 'customers.name', 'customers.area', 'customers.code')->orderByDesc('value_tp')->get()
            ->map(fn ($r) => (array) $r);

        return $this->shape([
            'distributor' => ['Distributor', 'text'], 'customer' => ['Customer', 'text'], 'area' => ['Area', 'text'], 'code' => ['Code', 'text'],
            'units' => ['Units', 'num'], 'bonus' => ['Bonus', 'num'], 'value_tp' => ['Value TP', 'money'],
            'sales_tax' => ['Sales tax', 'money'], 'withholding_tax' => ['WHT', 'money'],
        ], $rows);
    }

    private function bonus(Builder $lines): array
    {
        $rows = $lines->where('sale_lines.bonus_units', '>', 0)
            ->join('customers', 'customers.id', '=', 'sale_lines.customer_id')
            ->join('distributors', 'distributors.id', '=', 'sale_lines.distributor_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')->toBase()
            ->selectRaw('distributors.name AS distributor, customers.name AS customer, products.name AS product, sale_lines.units, sale_lines.bonus_units AS bonus,
                CASE WHEN sale_lines.bonus_from_file THEN \'Distributor file\' ELSE \'Scheme\' END AS source,
                sale_lines.bonus_units * sale_lines.cost_price AS cost, sale_lines.bonus_units * sale_lines.exfp_price AS value_exfp')
            ->orderBy('distributors.name')->orderBy('customers.name')->get()
            ->map(fn ($r) => (array) $r);

        return $this->shape([
            'distributor' => ['Distributor', 'text'], 'customer' => ['Customer', 'text'], 'product' => ['Product', 'text'], 'units' => ['Units', 'num'],
            'bonus' => ['Bonus units', 'num'], 'source' => ['Source', 'text'], 'cost' => ['Bonus at cost', 'money'], 'value_exfp' => ['Bonus at Ex-FP', 'money'],
        ], $rows);
    }

    /** Sales and incentive credited to each rep by the customer's share; customers with no rep shown separately. */
    private function incentive(CarbonImmutable $period, array $filters): array
    {
        $base = fn () => DB::table('sale_lines as s')->whereDate('s.period', $period)
            ->when($filters['scope'] ?? null, fn ($q, $ids) => $q->whereIn('s.distributor_id', $ids))
            ->when($filters['distributor'] ?? null, fn ($q, $id) => $q->where('s.distributor_id', $id))
            ->when($filters['product'] ?? null, fn ($q, $id) => $q->where('s.product_id', $id));

        $rows = $base()->join('customer_medical_rep as cr', 'cr.customer_id', '=', 's.customer_id')
            ->join('medical_reps as m', 'm.id', '=', 'cr.medical_rep_id')
            ->join('territories as t', 't.id', '=', 'm.territory_id')
            ->when($filters['rep'] ?? null, fn ($q, $id) => $q->where('m.id', $id))
            ->selectRaw('m.name AS rep, t.name AS territory, COUNT(DISTINCT s.customer_id) AS customers,
                SUM(s.units * cr.share_percent / 100) AS units, SUM(s.value_exfp * cr.share_percent / 100) AS value_exfp,
                SUM(s.incentive * cr.share_percent / 100) AS incentive')
            ->groupBy('m.id', 'm.name', 't.name')->orderByDesc('incentive')->get()->map(fn ($r) => (array) $r);

        if (! ($filters['rep'] ?? null)) {
            $waiting = $base()->whereNotExists(fn ($q) => $q->from('customer_medical_rep')->whereColumn('customer_medical_rep.customer_id', 's.customer_id'))
                ->selectRaw('COUNT(DISTINCT s.customer_id) AS customers, SUM(s.units) AS units, SUM(s.value_exfp) AS value_exfp, SUM(s.incentive) AS incentive')->first();

            if ($waiting && $waiting->customers > 0) {
                $rows->push(['rep' => 'No rep assigned yet', 'territory' => '–'] + (array) $waiting);
            }
        }

        return $this->shape([
            'rep' => ['Medical rep', 'text'], 'territory' => ['Territory', 'text'], 'customers' => ['Customers', 'num'], 'units' => ['Units credited', 'num'],
            'value_exfp' => ['Sales credited (Ex-FP)', 'money'], 'incentive' => ['Incentive', 'money'],
        ], $rows);
    }

    private function taxes(Builder $lines): array
    {
        $rows = $lines->join('products', 'products.id', '=', 'sale_lines.product_id')->toBase()
            ->selectRaw('products.name AS product, sale_lines.sales_tax_rate, sale_lines.withholding_tax_rate,
                SUM(sale_lines.value_tp) AS value_tp, SUM(sale_lines.sales_tax) AS sales_tax, SUM(sale_lines.withholding_tax) AS withholding_tax,
                SUM(sale_lines.value_tp + sale_lines.sales_tax + sale_lines.withholding_tax) AS gross')
            ->groupBy('products.id', 'products.name', 'sale_lines.sales_tax_rate', 'sale_lines.withholding_tax_rate')->orderBy('products.name')->get()
            ->map(fn ($r) => (array) $r);

        return $this->shape([
            'product' => ['Product', 'text'], 'sales_tax_rate' => ['Sales tax %', 'pct'], 'withholding_tax_rate' => ['WHT %', 'pct'],
            'value_tp' => ['Taxable value (TP)', 'money'], 'sales_tax' => ['Sales tax', 'money'], 'withholding_tax' => ['WHT', 'money'], 'gross' => ['Value incl. taxes', 'money'],
        ], $rows, ['sales_tax_rate' => fn () => null, 'withholding_tax_rate' => fn () => null]);
    }

    /** Adds totals for every numeric column; $override replaces a total that must not be summed. */
    private function shape(array $columns, $rows, array $override = []): array
    {
        $rows = collect($rows)->values();
        $totals = [];

        foreach ($columns as $key => [, $kind]) {
            $totals[$key] = in_array($kind, ['num', 'money'], true) ? $rows->sum(fn ($r) => (float) ($r[$key] ?? 0)) : null;
        }

        foreach ($override as $key => $fn) {
            $totals[$key] = $fn($totals);
        }

        return ['columns' => $columns, 'rows' => $rows->all(), 'totals' => $totals];
    }
}
