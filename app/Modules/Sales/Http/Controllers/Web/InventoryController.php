<?php

namespace App\Modules\Sales\Http\Controllers\Web;

use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Sales\Repositories\InventoryRepository;
use App\Modules\Sales\Repositories\StockReceiptRepository;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController
{
    public function index(Request $request, InventoryRepository $inventory, StockReceiptRepository $receipts, ProductRepository $products): View
    {
        $period = Period::fromRequest($request);

        return view('sales::inventory', [
            'period' => $period,
            'rows' => $inventory->stockFor($period),
            'products' => $products->active(['id', 'name', 'code']),
            'receipts' => $receipts->latest(),
            'canReceive' => $request->user()->hasRole('accounts'),
            'canEditRates' => $request->user()->hasRole('owner'),
        ]);
    }
}
