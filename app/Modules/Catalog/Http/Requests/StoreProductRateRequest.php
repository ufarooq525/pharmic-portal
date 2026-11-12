<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Support\Http\Requests\FormRequest;

class StoreProductRateRequest extends FormRequest
{
    protected array $roles = ['owner'];

    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date'],
            'exfp_price' => ['required', 'numeric', 'min:0'],
            'trade_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sales_tax_rate' => ['required', 'numeric', 'between:0,100'],
            'withholding_tax_rate' => ['required', 'numeric', 'between:0,100'],
            'incentive_rate' => ['required', 'numeric', 'between:0,100'],
            'bonus_buy' => ['required', 'integer', 'min:0', 'max:1000'],
            'bonus_free' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
