<?php

namespace App\Modules\Imports\Http\Requests;

use App\Modules\Distribution\Models\Distributor;
use App\Support\Http\Requests\FormRequest;
use Carbon\CarbonImmutable;

class StoreImportRequest extends FormRequest
{
    protected array $roles = ['accounts', 'data_entry'];

    public function authorize(): bool
    {
        return parent::authorize() && $this->user()->canSeeDistributor($this->integer('distributor_id'));
    }

    public function rules(): array
    {
        return [
            'distributor_id' => ['required', 'exists:distributors,id'],
            'period' => ['required', 'date_format:Y-m'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function distributor(): Distributor
    {
        return Distributor::findOrFail($this->validated('distributor_id'));
    }

    public function period(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->validated('period'));
    }
}
