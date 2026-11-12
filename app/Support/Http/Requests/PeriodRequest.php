<?php

namespace App\Support\Http\Requests;

use Carbon\CarbonImmutable;

/** Any request that needs a month as ?period=YYYY-MM. */
class PeriodRequest extends FormRequest
{
    public function rules(): array
    {
        return ['period' => ['required', 'date_format:Y-m']];
    }

    public function period(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->validated('period'));
    }
}
