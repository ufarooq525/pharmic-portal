<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductRate;
use App\Support\Actions\Action;

/**
 * A price or tax change is stored as a new dated rate. Months before the date keep their old
 * rates, and confirmed sales keep the rates they were posted with.
 */
class AddProductRate extends Action
{
    public function handle(Product $product, array $rate): ProductRate
    {
        $saved = $product->rates()->updateOrCreate(['effective_from' => $rate['effective_from']], $rate);
        $this->log('Changed rates', "{$product->name} from {$rate['effective_from']}: ST {$rate['sales_tax_rate']}%, WHT {$rate['withholding_tax_rate']}%, TP {$rate['trade_price']}, Ex-FP {$rate['exfp_price']}, cost {$rate['cost_price']}");

        return $saved;
    }
}
