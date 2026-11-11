<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pay for a month: base salary + incentive earned on confirmed sales to the rep's customers (split
 * by each customer's share, e.g. 50/50) + allowance − deductions.
 */
class PayrollBuilder
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
}
