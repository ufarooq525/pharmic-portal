<?php

namespace App\Modules\Distribution\Http\Requests;

use App\Modules\Distribution\Enums\BonusSource;
use App\Modules\Imports\Enums\ReportLayout;
use App\Support\Http\Requests\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistributorRequest extends FormRequest
{
    protected array $roles = ['owner'];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'header_name' => ['nullable', 'string', 'max:255'],
            'layout' => ['nullable', Rule::enum(ReportLayout::class)],
            'bonus_source' => ['required', Rule::enum(BonusSource::class)],
            'format_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
