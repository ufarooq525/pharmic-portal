<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'exfp_price' => 'float', 'trade_price' => 'float', 'cost_price' => 'float',
            'sales_tax_rate' => 'float', 'withholding_tax_rate' => 'float', 'incentive_rate' => 'float',
        ];
    }

    /** Bonus units by scheme, pro rata: 10+2 gives 1 free on 5 units. Returns earn nothing. */
    public function schemeBonus(float $units): int
    {
        return $units > 0 && $this->bonus_buy > 0 ? (int) floor($units * $this->bonus_free / $this->bonus_buy) : 0;
    }
}
