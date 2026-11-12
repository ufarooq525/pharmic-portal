<?php

namespace App\Modules\Sales\Http\Requests;

use App\Support\Http\Requests\FormRequest;
use App\Support\Period;
use Carbon\CarbonImmutable;

/** Report filters from the query string; the user's distributor scope is always applied. */
class ReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'distributor' => ['nullable', 'integer'],
            'product' => ['nullable', 'integer'],
            'rep' => ['nullable', 'integer'],
        ];
    }

    /** @return array{distributor: ?int, product: ?int, rep: ?int, scope: ?list<int>} */
    public function filters(): array
    {
        return [
            'distributor' => $this->integer('distributor') ?: null,
            'product' => $this->integer('product') ?: null,
            'rep' => $this->integer('rep') ?: null,
            'scope' => $this->visibleDistributorIds(),
        ];
    }

    public function period(): CarbonImmutable
    {
        return Period::fromRequest($this);
    }
}
