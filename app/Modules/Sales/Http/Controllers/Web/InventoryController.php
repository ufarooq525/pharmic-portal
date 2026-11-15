<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Sales\Repositories\InventoryRepository;
use App\Modules\Sales\Repositories\StockReceiptRepository;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController
{
    public function index(Request $request, InventoryRepository $inventory, ProductRepository $products): View
    {
        $period = Period::fromRequest($request);

        return view('sales::inventory', [
            'period' => $period,
            'rows' => $inventory->stockFor($period),
            'products' => $products->active(['id', 'name', 'code']),
            'canReceive' => $request->user()->can('stock.receive'),
            'canRevert' => $request->user()->can('stock.revert'),
            'canEditRates' => $request->user()->can('products.rates'),
        ]);
    }

    /** Inventory → Stock receipts tab. */
    public function receipts(Request $request, StockReceiptRepository $receipts): View
    {
        return view('sales::receipts', [
            'period' => Period::fromRequest($request),
            'receipts' => $receipts->latest(),
            'canRevert' => $request->user()->can('stock.revert'),
        ]);
    }

    /** One product's stock card for the month; opens in the inventory page's drawer. */
    public function card(Request $request, Product $product, InventoryRepository $inventory): View
    {
        $period = Period::fromRequest($request);

        return view('sales::stock-card', [
            'period' => $period,
            'product' => $product,
            'card' => $inventory->movements($product->id, $period),
            'canReceive' => $request->user()->can('stock.receive'),
            'canEditRates' => $request->user()->can('products.rates'),
        ]);
    }
}
