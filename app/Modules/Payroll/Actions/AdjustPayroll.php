<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Distribution\Models\MedicalRep;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;

/** A rep's allowance and deductions for one month (advance recovered, fine, travel allowance…). */
class AdjustPayroll extends Action
{
    public function handle(MedicalRep $rep, CarbonImmutable $period, float $allowance, float $deductions, ?string $note): PayrollAdjustment
    {
        $adjustment = PayrollAdjustment::updateOrCreate(
            ['medical_rep_id' => $rep->id, 'period' => $period->startOfMonth()->toDateString()],
            ['allowance' => $allowance, 'deductions' => $deductions, 'note' => $note],
        );
        $this->log('Changed payroll', "{$rep->name}, {$period->format('F Y')}: allowance ".fmt_rs($allowance).', deductions '.fmt_rs($deductions));

        return $adjustment;
    }
}
