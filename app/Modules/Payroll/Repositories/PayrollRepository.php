<?php

namespace App\Modules\Payroll\Repositories;

use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pay for a month: base salary + incentive earned on confirmed sales to the rep's customers (split
 * by each customer's share, e.g. 50/50) + allowance − deductions.
 */
class PayrollRepository
{
    /** @return list<array<string, mixed>> */
    public function month(CarbonImmutable $period, ?int $repId = null): array
    {
        $incentive = $this->incentiveQuery($period)->when($repId, fn ($q) => $q->where('cmr.medical_rep_id', $repId))
            ->groupBy('cmr.medical_rep_id')
            ->selectRaw('cmr.medical_rep_id, SUM(sale_lines.incentive * cmr.share_percent / 100) AS incentive, SUM(sale_lines.value_exfp * cmr.share_percent / 100) AS sales')
            ->get()->keyBy('medical_rep_id');
        $adjust = PayrollAdjustment::whereDate('period', $period->startOfMonth())->get()->keyBy('medical_rep_id');

        return MedicalRep::with('territory')->where('is_active', true)->when($repId, fn ($q) => $q->whereKey($repId))->orderBy('name')->get()
            ->map(function (MedicalRep $rep) use ($incentive, $adjust) {
                $inc = round((float) ($incentive[$rep->id]->incentive ?? 0), 2);
                $a = $adjust->get($rep->id);

                return [
                    'rep' => $rep,
                    'sales' => round((float) ($incentive[$rep->id]->sales ?? 0), 2),
                    'base' => $rep->base_salary,
                    'incentive' => $inc,
                    'allowance' => $a?->allowance ?? 0.0,
                    'deductions' => $a?->deductions ?? 0.0,
                    'note' => $a?->note,
                    'net' => round($rep->base_salary + $inc + ($a?->allowance ?? 0) - ($a?->deductions ?? 0), 2),
                ];
            })->all();
    }

    /** Incentive by customer and product for one rep's payslip. */
    public function breakdown(CarbonImmutable $period, int $repId): array
    {
        return $this->incentiveQuery($period)->where('cmr.medical_rep_id', $repId)
            ->join('customers', 'customers.id', '=', 'sale_lines.customer_id')
            ->groupBy('customers.id', 'customers.name', 'customers.area', 'cmr.share_percent')
            ->orderByRaw('SUM(sale_lines.incentive) DESC')
            ->selectRaw('customers.name, customers.area, cmr.share_percent, SUM(sale_lines.units) AS units,
                SUM(sale_lines.value_exfp * cmr.share_percent / 100) AS sales, SUM(sale_lines.incentive * cmr.share_percent / 100) AS incentive')
            ->get()->all();
    }

    private function incentiveQuery(CarbonImmutable $period)
    {
        return DB::table('sale_lines')
            ->join('customer_medical_rep as cmr', 'cmr.customer_id', '=', 'sale_lines.customer_id')
            ->whereBetween('sale_lines.period', [Period::start($period), Period::end($period)]);
    }

    /** A rep's own customers with this month's units, sales and incentive at the rep's share. */
    public function territoryCustomers(CarbonImmutable $period, int $repId)
    {
        return DB::table('customer_medical_rep as cmr')
            ->join('customers', 'customers.id', '=', 'cmr.customer_id')
            ->join('distributors', 'distributors.id', '=', 'customers.distributor_id')
            ->leftJoin('sale_lines', fn ($j) => $j->on('sale_lines.customer_id', '=', 'customers.id')
                ->whereBetween('sale_lines.period', [Period::start($period), Period::end($period)]))
            ->where('cmr.medical_rep_id', $repId)
            ->groupBy('customers.id', 'customers.name', 'customers.area', 'distributors.name', 'cmr.share_percent')
            ->orderByRaw('COALESCE(SUM(sale_lines.value_exfp), 0) DESC')
            ->selectRaw('customers.name, customers.area, distributors.name AS distributor, cmr.share_percent,
                COALESCE(SUM(sale_lines.units), 0) AS units, COALESCE(SUM(sale_lines.bonus_units), 0) AS bonus,
                COALESCE(SUM(sale_lines.value_exfp * cmr.share_percent / 100), 0) AS sales,
                COALESCE(SUM(sale_lines.incentive * cmr.share_percent / 100), 0) AS incentive')
            ->get();
    }
}
