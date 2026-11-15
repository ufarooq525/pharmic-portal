<?php

namespace App\Modules\Sales\Repositories;

use App\Modules\Catalog\Models\Product;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The stock table for a month: one grouped query over stock movements and one over sale lines,
 * then each product with its rates (eager-loaded) — three queries whatever the product count.
 */
class InventoryRepository
{
    public const LOW_STOCK_MONTHS = 1.5;

    /**
     * One product's stock card for a month: the opening balance, then each movement with a running balance.
     * Two queries; "opening" matches stockFor() (everything before the month, plus opening entries in it).
     *
     * @return array{opening: float, rows: list<array<string, mixed>>, in: float, out: float, closing: float}
     */
    public function movements(int $productId, CarbonImmutable $period): array
    {
        [$start, $end] = [Period::start($period), Period::end($period)];

        $opening = (float) DB::table('stock_movements')->where('product_id', $productId)
            ->where(fn ($q) => $q->where('moved_on', '<', $start)->orWhere(fn ($o) => $o->where('type', 'opening')->where('moved_on', '<=', $end)))
            ->sum('units');

        $balance = $opening;
        $rows = DB::table('stock_movements as m')
            ->leftJoin('imports as i', 'i.id', '=', 'm.import_id')
            ->leftJoin('distributors as d', 'd.id', '=', 'i.distributor_id')
            ->where('m.product_id', $productId)->where('m.type', '!=', 'opening')
            ->whereBetween('m.moved_on', [$start, $end])
            ->orderBy('m.moved_on')->orderBy('m.id')
            ->get(['m.id', 'm.moved_on', 'm.type', 'm.units', 'm.note', 'm.import_id', 'd.name as distributor'])
            // One distributor file's sales on a day are one line, not one per customer.
            ->groupBy(fn ($m) => $m->import_id ? $m->moved_on.'|'.$m->type.'|'.$m->import_id : 'm'.$m->id)
            ->map(fn ($group) => (object) [
                'moved_on' => $group->first()->moved_on,
                'type' => $group->first()->type,
                'units' => $group->sum(fn ($m) => (float) $m->units),
                'note' => $group->count() > 1 ? $group->count().' customer lines' : $group->first()->note,
                'distributor' => $group->first()->distributor,
            ])
            ->values()
            ->map(function ($m) use (&$balance) {
                $balance += (float) $m->units;

                return [
                    'date' => CarbonImmutable::parse($m->moved_on),
                    'type' => \App\Modules\Sales\Enums\StockMovementType::tryFrom($m->type),
                    'units' => (float) $m->units,
                    'detail' => collect([$m->distributor, $m->note])->filter()->join(' · '),
                    'balance' => $balance,
                ];
            })->all();

        $in = array_sum(array_map(fn ($r) => max(0, $r['units']), $rows));
        $out = array_sum(array_map(fn ($r) => max(0, -$r['units']), $rows));

        return ['opening' => $opening, 'rows' => $rows, 'in' => $in, 'out' => $out, 'closing' => $balance];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function stockFor(CarbonImmutable $period): Collection
    {
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

        // Sold and bonus split, and the taxes on this month's confirmed sales, per product.
        $sales = DB::table('sale_lines')->whereBetween('period', [$start, $end])->groupBy('product_id')
            ->selectRaw('product_id, SUM(CASE WHEN units > 0 THEN units ELSE 0 END) AS sold, SUM(bonus_units) AS bonus, SUM(sales_tax) AS sales_tax, SUM(withholding_tax) AS withholding_tax')
            ->get()->keyBy('product_id');

        return Product::orderBy('name')->with('rates')->get()->map(function (Product $p) use ($moves, $sales, $period) {
            $m = $moves->get($p->id);
            $s = $sales->get($p->id);
            $rate = $p->rateOn($period->endOfMonth());
            $closing = (float) ($m->closing ?? 0);
            $monthly = $m && $m->sale_months > 0 ? (float) $m->out_3m / max(1, min(3, (int) $m->sale_months)) : 0;
            $cover = $monthly > 0 ? $closing / $monthly : null;

            return [
                'name' => $p->name,
                'opening' => (float) ($m->opening ?? 0),
                'purchases' => (float) ($m->purchases ?? 0),
                'id' => $p->id,
                'code' => $p->code,
                'out' => (float) ($m->sold ?? 0),
                'sold' => (float) ($s->sold ?? 0),
                'bonus' => (float) ($s->bonus ?? 0),
                'sales_tax' => (float) ($s->sales_tax ?? 0),
                'withholding_tax' => (float) ($s->withholding_tax ?? 0),
                'returned' => (float) ($m->returned ?? 0),
                'adjusted' => (float) ($m->adjusted ?? 0),
                'closing' => $closing,
                'value_cost' => $closing * ($rate->cost_price ?? 0),
                'value_exfp' => $closing * ($rate->exfp_price ?? 0),
                'sales_tax_rate' => $rate?->sales_tax_rate,
                'withholding_tax_rate' => $rate?->withholding_tax_rate,
                'cover' => $cover,
                'level' => $closing <= 0 ? 'out' : ($cover !== null && $cover < self::LOW_STOCK_MONTHS ? 'low' : 'ok'),
            ];
        });
    }
}
