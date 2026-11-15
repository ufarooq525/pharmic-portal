<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Modules\Access\Enums\Permission;
use App\Support\Http\Requests\FormRequest;
use Carbon\CarbonImmutable;

class AdjustPayrollRequest extends FormRequest
{
    protected ?Permission $permission = Permission::PayrollAdjust;

    public function rules(): array
    {
        return [
            'period' => ['required', 'date_format:Y-m'],
            'allowance' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'deductions' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function period(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->validated('period'));
    }
}
