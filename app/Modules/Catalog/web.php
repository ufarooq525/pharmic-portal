<?php

use App\Modules\Catalog\Http\Controllers\Web\ProductPageController;
use Illuminate\Support\Facades\Route;

// Products, prices and taxes live under Inventory (they used to be under Master data at /products).
Route::middleware('auth')->get('products', fn (\Illuminate\Http\Request $r) => redirect()->to('/inventory/products'.($r->getQueryString() ? '?'.$r->getQueryString() : ''), 301));

Route::middleware('auth')->prefix('inventory/products')->name('products.')->group(function () {
    Route::get('/', [ProductPageController::class, 'index'])->middleware('permission:products.view')->name('index');
    Route::post('{product}/rates', [ProductPageController::class, 'storeRate'])->middleware('permission:products.rates')->name('rates');
});
