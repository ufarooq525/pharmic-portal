<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollAdjustment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period' => 'date', 'allowance' => 'float', 'deductions' => 'float'];
    }
}
