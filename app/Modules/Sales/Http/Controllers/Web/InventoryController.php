<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Catalog\Models\Product;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Stock for the month, built only from stock movements: opening, purchases, units sold plus bonus
 * (from confirmed imports), returns and adjustments. Every figure traces back to its movement.
 */
class InventoryController
{
    public function index(Request $request): View
    {
        $period = Period::fromRequest($request);
        [$start, $end] = [Period::start($period), Period::end($period)];
        $from3 = Period::start($period->subMonths(2));
        $in = "moved_on >= ? AND moved_on <= ?";

        $moves = DB::table('stock_movements')->selectRaw("product_id,
            SUM(CASE WHEN moved_on < ? OR (type = 'opening' AND moved_on <= ?) THEN units ELSE 0 END) AS opening,
            SUM(CASE WHEN {$in} AND type = 'purchase' THEN units ELSE 0 END) AS purchases,
            SUM(CASE WHEN {$in} AND type = 'sale' THEN -units ELSE 0 END) AS sold,
            SUM(CASE WHEN {$in} AND type = 'return' THEN units ELSE 0 END) AS returned,
            SUM(CASE WHEN {$in} AND type = 'adjustment' THEN units ELSE 0 END) AS adjusted,
            SUM(CASE WHEN moved_on <= ? THEN units ELSE 0 END) AS closing,
            SUM(CASE WHEN {$in} AND type IN ('sale', 'return') THEN -units ELSE 0 END) AS out_3m,
            COUNT(DISTINCT CASE WHEN {$in} AND type = 'sale' THEN moved_on END) AS sale_months",
            [$start, $end, $start, $end, $start, $end, $start, $end, $start, $end, $end, $from3, $end, $from3, $end])
            ->groupBy('product_id')->get()->keyBy('product_id');

        $rows = Product::orderBy('name')->with('rates')->get()->map(function (Product $p) use ($moves, $period) {
            $m = $moves->get($p->id);
            $rate = $p->rateOn($period->endOfMonth());
            $closing = (float) ($m->closing ?? 0);
            $monthly = $m && $m->sale_months > 0 ? (float) $m->out_3m / max(1, min(3, (int) $m->sale_months)) : 0;
            $cover = $monthly > 0 ? $closing / $monthly : null;

            return [
                'name' => $p->name,
                'opening' => (float) ($m->opening ?? 0),
                'purchases' => (float) ($m->purchases ?? 0),
                'sold' => (float) ($m->sold ?? 0),
                'returned' => (float) ($m->returned ?? 0),
                'adjusted' => (float) ($m->adjusted ?? 0),
                'closing' => $closing,
                'value_cost' => $closing * ($rate->cost_price ?? 0),
                'value_exfp' => $closing * ($rate->exfp_price ?? 0),
                'sales_tax_rate' => $rate?->sales_tax_rate,
                'withholding_tax_rate' => $rate?->withholding_tax_rate,
                'cover' => $cover,
                'level' => $closing <= 0 ? 'out' : ($cover !== null && $cover < 1.5 ? 'low' : 'ok'),
            ];
        });

        return view('sales::inventory', ['period' => $period, 'rows' => $rows]);
    }
}
