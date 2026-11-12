<?php

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Catalog\Models\ProductRate */
class ProductRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'exfp_price' => (float) $this->exfp_price,
            'trade_price' => (float) $this->trade_price,
            'cost_price' => (float) $this->cost_price,
            'sales_tax_rate' => (float) $this->sales_tax_rate,
            'withholding_tax_rate' => (float) $this->withholding_tax_rate,
            'incentive_rate' => (float) $this->incentive_rate,
            'bonus_scheme' => ['buy' => (int) $this->bonus_buy, 'free' => (int) $this->bonus_free],
        ];
    }
}
