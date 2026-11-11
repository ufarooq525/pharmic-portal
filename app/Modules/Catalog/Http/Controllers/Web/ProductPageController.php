<?php

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Modules\Catalog\Models\Product;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductPageController
{
    public function index(Request $request): View
    {
        $period = Period::fromRequest($request);

        return view('catalog::products', [
            'period' => $period,
            'products' => Product::orderBy('name')->with(['rates' => fn ($q) => $q->orderByDesc('effective_from'), 'aliases:id,product_id,name_in_file'])->get(),
            'canEdit' => $request->user()->hasRole(),
            'editing' => $request->integer('edit') ?: null,
        ]);
    }

    /**
     * A price or tax change is stored as a new dated rate. Months before the date keep their old
     * rates, and confirmed sales keep the rates they were posted with.
     */
    public function storeRate(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'exfp_price' => ['required', 'numeric', 'min:0'],
            'trade_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sales_tax_rate' => ['required', 'numeric', 'between:0,100'],
            'withholding_tax_rate' => ['required', 'numeric', 'between:0,100'],
            'incentive_rate' => ['required', 'numeric', 'between:0,100'],
            'bonus_buy' => ['required', 'integer', 'min:0', 'max:1000'],
            'bonus_free' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $product->rates()->updateOrCreate(['effective_from' => $data['effective_from']], $data);

        return redirect()->route('products.index', ['period' => $request->query('period')])
            ->with('status', "{$product->name}: new rates apply from ".date('j M Y', strtotime($data['effective_from'])).'.');
    }
}
