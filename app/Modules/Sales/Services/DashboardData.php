<?php

namespace App\Modules\Sales\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Models\Import;
use App\Modules\Imports\Models\ImportRow;
use App\Modules\Sales\Models\SaleLine;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Everything the owner needs to close the month and decide what to do next, in one place:
 * what is still missing, what changed against last month, where the money comes from,
 * what stock will run out, and what is owed in tax and incentive.
 */
class DashboardData
{
    public const LOW_STOCK_MONTHS = 1.5;

    /** @var array<string, array> */
    private array $stockMemo = [];

    public function for(CarbonImmutable $period): array
    {
        // Cached per month; any upload, confirm or stock change produces a new key.
        $stamp = implode('|', [Import::max('updated_at'), DB::table('stock_movements')->max('id'), DB::table('customer_medical_rep')->max('updated_at')]);

        return Cache::remember('dashboard:'.$period->format('Y-m').':'.md5($stamp), now()->addMinutes(10), fn () => $this->build($period));
    }

    private function build(CarbonImmutable $period): array
    {
        $previous = $period->subMonth();
        $now = $this->totals($period);
        $before = $this->totals($previous);

        return [
            'totals' => $now,
            'deltas' => collect($now)->map(fn ($v, $k) => ($before[$k] ?? 0) > 0 ? ($v - $before[$k]) / $before[$k] * 100 : null)->all(),
            'hasPrevious' => $before['value_exfp'] > 0,
            'checklist' => $this->checklist($period),
            'actions' => $this->actions($period),
            'trend' => $this->trend($period),
            'byDistributor' => $this->byDistributor($period),
            'byRep' => $this->byRep($period),
            'byProduct' => $this->byProduct($period),
            'topCustomers' => $this->topCustomers($period),
            'stock' => $this->stockCover($period),
        ];
    }

    private function totals(CarbonImmutable $period): array
    {
        $row = SaleLine::whereDate('period', $period)->toBase()->selectRaw('
            COALESCE(SUM(units), 0) AS units, COALESCE(SUM(bonus_units), 0) AS bonus_units,
            COALESCE(SUM(value_exfp), 0) AS value_exfp, COALESCE(SUM(value_tp), 0) AS value_tp,
            COALESCE(SUM(value_exfp - units * cost_price), 0) AS gross_profit,
            COALESCE(SUM(bonus_units * cost_price), 0) AS bonus_cost,
            COALESCE(SUM(sales_tax), 0) AS sales_tax, COALESCE(SUM(withholding_tax), 0) AS withholding_tax,
            COALESCE(SUM(incentive), 0) AS incentive, COUNT(DISTINCT customer_id) AS customers')->first();

        return array_map('floatval', (array) $row);
    }

    /** One row per distributor: has this month's file arrived, and where is it in the flow. */
    private function checklist(CarbonImmutable $period): array
    {
        $imports = Import::whereDate('period', $period)->latest('id')->get(['id', 'distributor_id', 'status', 'period_from'])->unique('distributor_id')->keyBy('distributor_id');

        return Distributor::orderBy('name')->get(['id', 'name', 'city'])->map(function (Distributor $d) use ($imports) {
            $import = $imports->get($d->id);
            [$label, $tone] = match ($import?->status) {
                Import::CONFIRMED => ['Confirmed', 'ok'],
                Import::NEEDS_REVIEW => ['Needs review', 'warn'],
                Import::BLOCKED => ['Blocked', 'bad'],
                default => ['File not received', 'mute'],
            };

            return ['name' => $d->name, 'city' => $d->city, 'label' => $label, 'tone' => $tone, 'import_id' => $import?->id, 'distributor_id' => $d->id];
        })->all();
    }

    /** What someone has to do before the month can close, most urgent first. */
    private function actions(CarbonImmutable $period): array
    {
        $actions = [];
        $q = ['period' => $period->format('Y-m')];
        $pending = Import::whereDate('period', $period)->where('status', '!=', Import::CONFIRMED)->with('distributor:id,name')->get(['id', 'distributor_id', 'status']);

        foreach ($pending->where('status', Import::BLOCKED) as $i) {
            $actions[] = ['tone' => 'bad', 'text' => "{$i->distributor->name}: the file failed a check (wrong month, name or totals).", 'url' => route('imports.show', $i), 'cta' => 'Open'];
        }

        $unmapped = ImportRow::whereIn('import_id', $pending->pluck('id'))->whereNull('product_id')->distinct()->count('product_name');
        if ($unmapped) {
            $actions[] = ['tone' => 'bad', 'text' => "{$unmapped} product ".str('name')->plural($unmapped).' in uploaded files not linked to a product yet.', 'url' => route('imports.index', $q), 'cta' => 'Map'];
        }

        foreach ($pending->where('status', Import::NEEDS_REVIEW) as $i) {
            $actions[] = ['tone' => 'warn', 'text' => "{$i->distributor->name}: uploaded, waiting for review and confirmation.", 'url' => route('imports.show', $i), 'cta' => 'Review'];
        }

        $missing = Distributor::whereDoesntHave('imports', fn ($w) => $w->whereDate('period', $period))->pluck('name');
        if ($missing->isNotEmpty()) {
            $actions[] = ['tone' => 'mute', 'text' => 'No file yet from '.$missing->join(', ', ' and ').'.', 'url' => route('imports.index', $q), 'cta' => 'Upload'];
        }

        $noRep = SaleLine::whereDate('period', $period)->whereDoesntHave('customer.medicalReps')->distinct()->count('customer_id');
        if ($noRep) {
            $actions[] = ['tone' => 'warn', 'text' => "{$noRep} customers who bought this month have no medical rep, so their incentive is on hold.", 'url' => route('customers.index', $q + ['status' => 'needs_rep']), 'cta' => 'Assign'];
        }

        foreach (array_filter($this->stockCover($period), fn ($s) => $s['level'] !== 'ok') as $s) {
            $actions[] = ['tone' => $s['level'] === 'out' ? 'bad' : 'warn', 'text' => $s['level'] === 'out'
                ? "{$s['name']} is out of stock."
                : "{$s['name']} has about ".fmt_num($s['cover'], 1).' months of stock left ('.fmt_num($s['closing']).' units).', 'url' => route('inventory.index', $q), 'cta' => 'Stock'];
        }

        return $actions;
    }

    /** Sales at Ex-FP for the six months up to the selected one. Months without data show as zero. */
    private function trend(CarbonImmutable $period): array
    {
        $from = $period->subMonths(5);
        $rows = SaleLine::whereDate('period', '>=', $from)->whereDate('period', '<=', $period)
            ->toBase()->selectRaw('period, SUM(value_exfp) AS value, SUM(units) AS units')->groupBy('period')->get()
            ->keyBy(fn ($r) => substr((string) $r->period, 0, 7));

        return collect(range(0, 5))->map(function ($i) use ($from, $rows) {
            $month = $from->addMonths($i);

            return ['label' => $month->format('M'), 'month' => $month->format('Y-m'), 'value' => (float) ($rows[$month->format('Y-m')]->value ?? 0)];
        })->all();
    }

    private function byDistributor(CarbonImmutable $period): array
    {
        return SaleLine::whereDate('period', $period)
            ->join('distributors', 'distributors.id', '=', 'sale_lines.distributor_id')
            ->toBase()->selectRaw('distributors.name, SUM(sale_lines.value_exfp) AS value, SUM(sale_lines.units) AS units, COUNT(DISTINCT sale_lines.customer_id) AS customers')
            ->groupBy('distributors.id', 'distributors.name')->orderByDesc('value')->get()
            ->map(fn ($r) => ['label' => $r->name, 'value' => (float) $r->value, 'display' => fmt_rs($r->value), 'sub' => fmt_num($r->units).' units · '.$r->customers.' customers'])->all();
    }

    /** Sales and incentive credited to each rep, split by the customer's share. */
    private function byRep(CarbonImmutable $period): array
    {
        return DB::table('sale_lines as s')
            ->join('customer_medical_rep as cr', 'cr.customer_id', '=', 's.customer_id')
            ->join('medical_reps as m', 'm.id', '=', 'cr.medical_rep_id')
            ->join('territories as t', 't.id', '=', 'm.territory_id')
            ->whereDate('s.period', $period)
            ->selectRaw('m.name, t.name AS territory, SUM(s.value_exfp * cr.share_percent / 100) AS value, SUM(s.incentive * cr.share_percent / 100) AS incentive')
            ->groupBy('m.id', 'm.name', 't.name')->orderByDesc('value')->get()
            ->map(fn ($r) => ['label' => $r->name, 'value' => (float) $r->value, 'display' => fmt_rs($r->value), 'sub' => $r->territory.' · incentive '.fmt_rs($r->incentive)])->all();
    }

    private function byProduct(CarbonImmutable $period): array
    {
        return SaleLine::whereDate('period', $period)
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->toBase()->selectRaw('products.name, SUM(sale_lines.units) AS units, SUM(sale_lines.bonus_units) AS bonus, SUM(sale_lines.value_exfp) AS value')
            ->groupBy('products.id', 'products.name')->orderByDesc('value')->get()
            ->map(fn ($r) => ['label' => $r->name, 'value' => (float) $r->value, 'display' => fmt_rs($r->value), 'sub' => fmt_num($r->units).' units + '.fmt_num($r->bonus).' bonus'])->all();
    }

    private function topCustomers(CarbonImmutable $period): array
    {
        return SaleLine::whereDate('period', $period)
            ->join('customers', 'customers.id', '=', 'sale_lines.customer_id')
            ->join('distributors', 'distributors.id', '=', 'sale_lines.distributor_id')
            ->toBase()->selectRaw('customers.name, customers.area, distributors.name AS distributor, SUM(sale_lines.units) AS units, SUM(sale_lines.value_tp) AS value')
            ->groupBy('customers.id', 'customers.name', 'customers.area', 'distributors.name')->orderByDesc('value')->limit(8)->get()
            ->map(fn ($r) => (array) $r)->all();
    }

    /**
     * Closing stock and how long it lasts at the recent pace (average units out per month over the
     * last three months that had sales). This is the reorder signal.
     */
    private function stockCover(CarbonImmutable $period): array
    {
        return $this->stockMemo[$period->format('Y-m')] ??= (function () use ($period) {
            $end = Period::end($period);
            $from = Period::start($period->subMonths(2));

            $rows = DB::table('stock_movements')->selectRaw("product_id,
                SUM(CASE WHEN moved_on <= ? THEN units ELSE 0 END) AS closing,
                SUM(CASE WHEN moved_on >= ? AND moved_on <= ? AND type IN ('sale', 'return') THEN -units ELSE 0 END) AS out_3m,
                COUNT(DISTINCT CASE WHEN moved_on >= ? AND moved_on <= ? AND type = 'sale' THEN moved_on END) AS sale_months", [$end, $from, $end, $from, $end])
                ->groupBy('product_id')->get()->keyBy('product_id');

            return Product::where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(function (Product $p) use ($rows) {
                $r = $rows->get($p->id);
                $closing = (float) ($r->closing ?? 0);
                $monthly = $r && $r->sale_months > 0 ? (float) $r->out_3m / max(1, min(3, (int) $r->sale_months)) : 0;
                $cover = $monthly > 0 ? $closing / $monthly : null;
                $level = $closing <= 0 ? 'out' : ($cover !== null && $cover < self::LOW_STOCK_MONTHS ? 'low' : 'ok');

                return ['name' => $p->name, 'closing' => $closing, 'monthly' => $monthly, 'cover' => $cover, 'level' => $level];
            })->sortBy(fn ($s) => $s['cover'] ?? PHP_INT_MAX)->values()->all();
        })();
    }
}
