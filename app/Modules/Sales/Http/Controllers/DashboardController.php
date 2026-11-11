<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Imports\Models\Import;
use App\Modules\Sales\Models\SaleLine;
use App\Modules\Sales\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    private const LOW_STOCK = 100;

    public function __invoke(Request $request): JsonResponse
    {
        $period = CarbonImmutable::createFromFormat('!Y-m', $request->validate(['period' => ['required', 'date_format:Y-m']])['period']);
        $lines = SaleLine::whereDate('period', $period);

        $totals = (clone $lines)->selectRaw('
            COALESCE(SUM(units), 0) AS units, COALESCE(SUM(bonus_units), 0) AS bonus_units,
            COALESCE(SUM(value_exfp), 0) AS value_exfp, COALESCE(SUM(value_tp), 0) AS value_tp,
            COALESCE(SUM(value_exfp - units * cost_price), 0) AS gross_profit,
            COALESCE(SUM(sales_tax), 0) AS sales_tax, COALESCE(SUM(withholding_tax), 0) AS withholding_tax,
            COALESCE(SUM(incentive), 0) AS incentive')->first();

        $byDistributor = (clone $lines)->select('distributor_id', DB::raw('SUM(value_exfp) AS value_exfp'), DB::raw('SUM(units) AS units'))
            ->groupBy('distributor_id')->with('distributor:id,name')->orderByDesc('value_exfp')->get()
            ->map(fn ($r) => ['name' => $r->distributor->name, 'value_exfp' => (float) $r->value_exfp, 'units' => (float) $r->units]);

        $byProduct = (clone $lines)->select('product_id', DB::raw('SUM(units) AS units'), DB::raw('SUM(value_exfp) AS value_exfp'))
            ->groupBy('product_id')->with('product:id,name')->orderByDesc('units')->get()
            ->map(fn ($r) => ['name' => $r->product->name, 'units' => (float) $r->units, 'value_exfp' => (float) $r->value_exfp]);

        $topCustomers = (clone $lines)->select('customer_id', DB::raw('SUM(units) AS units'), DB::raw('SUM(value_tp) AS value_tp'))
            ->groupBy('customer_id')->with('customer:id,name,distributor_id', 'customer.distributor:id,name')->orderByDesc('value_tp')->limit(6)->get()
            ->map(fn ($r) => ['name' => $r->customer->name, 'distributor' => $r->customer->distributor->name, 'units' => (float) $r->units, 'value_tp' => (float) $r->value_tp]);

        $imports = Import::whereDate('period', $period)->latest('id')->get()->unique('distributor_id')->keyBy('distributor_id');
        $checklist = Distributor::orderBy('name')->get()->map(fn (Distributor $d) => [
            'name' => $d->name, 'city' => $d->city, 'status' => $imports->get($d->id)?->status ?? 'missing',
        ]);

        $stock = StockMovement::whereDate('moved_on', '<=', $period->endOfMonth())->select('product_id', DB::raw('SUM(units) AS units'))->groupBy('product_id')->pluck('units', 'product_id');
        $stockWatch = Product::where('is_active', true)->get(['id', 'name'])
            ->map(fn (Product $p) => ['name' => $p->name, 'closing_units' => (float) ($stock[$p->id] ?? 0)])
            ->sortBy('closing_units')->take(6)->values()
            ->map(fn ($s) => $s + ['level' => $s['closing_units'] <= 0 ? 'out' : ($s['closing_units'] < self::LOW_STOCK ? 'low' : 'ok')]);

        return response()->json(['data' => [
            'period' => $period->format('Y-m'),
            'totals' => collect($totals->getAttributes())->map(fn ($v) => (float) $v),
            'by_distributor' => $byDistributor,
            'by_product' => $byProduct,
            'top_customers' => $topCustomers,
            'checklist' => $checklist,
            'stock_watch' => $stockWatch,
            'customers_without_rep' => Customer::doesntHave('medicalReps')->count(),
        ]]);
    }
}
